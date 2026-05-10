# Flujo Carrito — iStore Chile

Este documento detalla la arquitectura, el estado inicial de error, la decisión de diseño y la solución implementada para el manejo del carrito de compras en la tienda iStore Chile.

---

## 1. Estado actual
Al ingresar a la tienda iStore Chile como visitante (invitado), la consola del navegador reportaba un error recurrente:
`GET https://istore-chile-production.up.railway.app/api/cart 401 (Unauthorized)`

### Causa raíz:
1. En versiones anteriores, los endpoints del carrito (`/api/cart`) estaban protegidos por el middleware `auth:sanctum` de Laravel.
2. Esto impedía que un visitante sin cuenta pudiese interactuar con el catálogo de productos de forma fluida (añadiendo artículos a una bolsa temporal antes de decidirse a iniciar sesión o crear una cuenta).
3. Aunque en commits recientes en el repositorio local se eliminó la protección `auth:sanctum` de las rutas principales de `/api/cart` para dar paso a un flujo de invitado, la producción en Railway mantenía comportamientos desalineados o existían pequeñas incompatibilidades de mapeo de propiedades entre el recurso JSON devuelto por el backend (`CartItemResource`) y las propiedades consumidas por el componente React (`Tienda.jsx`).

---

## 2. Decisión técnica
Hemos optado por aplicar de forma robusta e integral la **Opción A — Guest cart** (Carrito accesible para invitados).

### Razones fundamentales:
* **Conversión y UX Superior:** Forzar al usuario a iniciar sesión antes de que pueda agregar un solo producto a su bolsa genera fricción y reduce significativamente las ventas. El flujo moderno permite a cualquier visitante acumular productos e iniciar sesión únicamente en el paso final del checkout.
* **Infraestructura pre-existente óptima:** El backend de iStore Chile ya contaba con una excelente lógica híbrida (`CartService` y `resolveIdentity` en `CartController`) preparada para manejar tanto usuarios logueados como sesiones temporales (`X-Session-Id` enviada en los encabezados de fetch).
* **Flujo auto-sanador:** Al iniciar sesión exitosamente, el frontend invoca de forma automática un proceso de sincronización (`/api/cart/sync`) que unifica los ítems de invitado con el carrito del usuario registrado sin perder información.

---

## 3. Cambios en backend

Para solidificar la **Opción A** de forma impecable, realizamos los siguientes ajustes de refactorización y control de calidad en el backend:

### A. Mapeo de Propiedades Plano en `CartItemResource.php`
El componente frontend `Tienda.jsx` busca directamente las propiedades `product_name` y `product_image` en cada ítem del carrito (ej. `item.product_name` e `item.product_image`). Sin embargo, el recurso del backend anidaba estos valores dentro de un sub-objeto `product`.
* **Solución:** Agregamos de manera plana las propiedades `product_name` y `product_image` en la raíz de `CartItemResource.php` para asegurar total compatibilidad con el frontend sin alterar la estructura existente de `product` para compatibilidad retroactiva.

```php
// app/Http/Resources/CartItemResource.php
public function toArray(Request $request): array
{
    return [
        'id'            => $this->id,
        'product_id'    => $this->product_id,
        'quantity'      => $this->quantity,
        'subtotal'      => $this->product->price * $this->quantity,
        'product_name'  => $this->product->name,
        'product_image' => $this->product->primaryImage?->url,

        // Datos del producto (para renderizar en el frontend sin fetch extra)
        'product' => [ ... ]
    ];
}
```

### B. Corrección Lógica de Pruebas Unitarias en `CartControllerTest.php`
La suite de pruebas de PHPUnit contenía aserciones erróneas que buscaban el campo `subtotal` a nivel de raíz del carrito (`data.subtotal`), cuando el valor correcto correspondiente al monto acumulado global es `total_price` (`data.total_price`).
* **Solución:** Reemplazamos las aserciones de `data.subtotal` por `data.total_price` en los métodos de prueba de añadir y actualizar ítem, garantizando que el pipeline de integración continua (CI) pase sin fallos lógicos.

---

## 4. Cambios en frontend

El frontend ya cuenta con una excelente arquitectura desacoplada y un cliente centralizado en `src/lib/api.js`. Analizamos los componentes y validamos su comportamiento:

1. **Generación automática de Identificador Temporal:**
   * En `api.js`, la función `getSessionId()` genera un UUID único y persistente utilizando `crypto.randomUUID()` la primera vez que un visitante accede, guardándolo en el `localStorage` con la clave `istore_session_id`.
2. **Inyección de Encabezados:**
   * En cada petición fetch que se realiza mediante el wrapper `api`, se incluye automáticamente el encabezado `'X-Session-Id': sessionId` y, en caso de estar autenticado, se añade el token Bearer correspondiente.
3. **Manejo Desacoplado de Errores 401:**
   * El interceptor/wrapper global de `apiRequest` en `api.js` detecta respuestas `401 Unauthorized`. Para evitar romper el flujo de visitas anónimas públicas, el limpiador automático de tokens solo se ejecuta si el usuario estaba previamente autenticado (es decir, si existía un token guardado en el cliente). Esto garantiza que si una ruta pública responde un error de auth por tokens expirados viejos de localstorage, el sistema se limpie a si mismo disparando el evento `'auth:expired'` hacia `AuthContext` de forma transparente.
4. **Sincronización Automática (Merge) al iniciar sesión:**
   * Al loguearse o registrarse, el `CartContext` escucha el cambio de estado de `isAuthenticated` y dispara un POST asíncrono a `/api/cart/sync` enviando el `session_id` temporal. El backend une los carritos y el frontend actualiza el estado de la bolsa en pantalla de inmediato.

---

## 5. Validación final

* **Análisis de Rutas:** El archivo `routes/api.php` expone el grupo de rutas `cart` de forma pública (fuera del middleware `auth:sanctum`), habilitando el acceso sin token.
* **Consistencia de Datos:** Con la incorporación de `product_name` y `product_image` en `CartItemResource.php`, la barra lateral del carrito (Tu Bolsa Drawer) renderizará perfectamente el nombre y la foto del producto agregado sin campos vacíos ni placeholders rotos.
* **Robustez de la Suite de Pruebas:** Las pruebas automatizadas del carrito en el backend ahora están sincronizadas con la estructura real del recurso, facilitando despliegues exitosos y seguros en Railway.
* **Experiencia de Usuario (UX) Impecable:** El usuario puede agregar productos sin iniciar sesión, ver su carrito, y solo al presionar "PAGAR AHORA" se desplegará el aviso amistoso pidiendo iniciar sesión, redirigiéndolo de forma limpia a `/mi-cuenta`.
