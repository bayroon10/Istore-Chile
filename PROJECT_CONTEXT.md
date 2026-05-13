# PROJECT_CONTEXT.md — iStore Chile
> Generado por Antigravity el 13 de mayo de 2026. Fuente de verdad del proyecto.

## 👤 Desarrollador
- Nombre: Bairon Meneses
- Universidad: DUOC UC, Santiago, Chile
- GitHub: https://github.com/bayroon10/Istore-Chile
- Frontend producción: https://istore-chile.vercel.app

## 📦 Stack Real
| Capa | Tecnología | Versión | Notas |
| :--- | :--- | :--- | :--- |
| **Backend** | PHP / Laravel | 8.3 / 12.x | API REST Stateless, Sanctum auth. |
| **Frontend** | React / Vite | 19.x / 7.x | SPA, Custom Hooks, Tailwind v4. |
| **Base de Datos**| PostgreSQL | - | Recomendado Neon.tech en prod (dev usa SQLite In-Memory en tests). |
| **IA** | Gemini API | - | Asistente virtual (Santi) integrado. |
| **Pagos** | Stripe | - | Checkout y Webhooks implementados (modo prueba). |
| **Imágenes** | Cloudinary | - | Almacenamiento de imágenes de productos. |
| **Automatización**| n8n | - | Webhooks tras pago exitoso. |

## 🏗️ Estructura del Proyecto
```text
/labstock-pro (iStore Chile)
├── backend-php/                  # API REST en Laravel
│   ├── app/Http/Controllers/Api/ # Controladores principales (Productos, Órdenes, Dashboard, Chatbot)
│   ├── database/migrations/      # Esquemas de BD
│   ├── Dockerfile                # Configuración de imagen productiva (PHP-FPM + Nginx)
│   ├── entrypoint.sh             # Script de arranque (Migraciones + Caches + FPM/Nginx)
│   └── nginx.conf                # Configuración del servidor web
└── frontend-react/               # SPA en React
    ├── src/components/           # Componentes UI (Chatbot, Cards, Navbar)
    ├── src/contexts/             # Estado global (AuthContext, CartContext)
    ├── src/hooks/                # Custom hooks (useDebounce, useScrollShrink)
    ├── src/pages/                # Vistas principales (Tienda, Admin, Login)
    └── src/lib/api.js            # Wrapper de fetch para llamadas a la API
```

## ⚙️ Variables de Entorno

### Backend (.env)
- `APP_NAME="iStore Chile"`: Nombre de la aplicación.
- `APP_ENV=production`: Entorno (production/local).
- `APP_KEY`: Clave de cifrado de Laravel.
- `APP_DEBUG=false`: Modo debug apagado en producción.
- `APP_URL`: URL del backend (ej. Koyeb).
- `FRONTEND_URL`: URL del frontend en Vercel.
- `ALLOWED_ORIGINS`: Lista separada por comas (ej. `https://istore-chile.vercel.app`).
- `DB_CONNECTION=pgsql`: Motor de base de datos.
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: Credenciales DB (Neon.tech).
- `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`: Credenciales de Stripe.
- `CLOUDINARY_URL`: URL de conexión a Cloudinary.
- `GEMINI_API_KEY`: Key para el Chatbot.
- `N8N_WEBHOOK_URL`: URL del webhook para notificar pagos.

### Frontend (.env)
- `VITE_API_URL`: URL base del backend desplegado.
- `VITE_APP_NAME`: "iStore Chile".
- `VITE_STRIPE_PUBLISHABLE_KEY`: Clave pública de Stripe para inicializar el checkout.

## 🔌 Endpoints de la API

| Método | Ruta | Descripción | Auth requerida |
| :--- | :--- | :--- | :--- |
| **GET** | `/api/products` | Lista catálogo paginado con filtros. | ❌ No |
| **GET** | `/api/categories` | Lista categorías. | ❌ No |
| **POST** | `/api/login` | Login de administrador. | ❌ No |
| **POST** | `/api/cliente/login` | Login de cliente. | ❌ No |
| **POST** | `/api/chatbot` | Envía msj al asistente Gemini. | ❌ No (Rate limit) |
| **POST** | `/api/webhooks/stripe` | Recibe eventos de Stripe. | ❌ No (Signature) |
| **POST** | `/api/orders/checkout` | Procesa el carrito a orden. | 🔒 Sanctum (User) |
| **GET** | `/api/orders` | Historial de órdenes del cliente. | 🔒 Sanctum (User) |
| **GET** | `/api/estadisticas` | KPIs principales del dashboard. | 🛡️ Sanctum (Admin) |
| **CRUD**| `/api/products/*` | Gestión de inventario. | 🛡️ Sanctum (Admin) |

## 🚨 Errores Encontrados

- **[🔴 CRÍTICO] `backend-php/nginx.conf` y `backend-php/config/cors.php`**: Conflicto masivo de CORS. `nginx.conf` inyecta headers de CORS manualmente y usa `fastcgi_hide_header` para borrar los de Laravel. Esto provoca fallos de sincronización y respuestas 500/CORS en Vercel ("SINCRONIZANDO..."). 
  → **Solución recomendada**: Eliminar todo el bloque de headers CORS y variables estáticas `$cors_origin` en `nginx.conf`. Dejar que el middleware de Laravel (`cors.php`) controle exclusivamente los orígenes permitidos.

- **[🔴 CRÍTICO] `backend-php/app/Http/Controllers/Api/ChatbotController.php`**: El prompt del sistema inyecta **TODO** el catálogo de productos activos (`Product::get()`) en cada solicitud. Con muchos productos, esto causará un error por límite de tokens en la API de Gemini (Error 500).
  → **Solución recomendada**: Filtrar productos en base al mensaje del usuario o limitar el contexto a las categorías más relevantes (implementar RAG básico).

- **[🔴 CRÍTICO] `frontend-react/.env`**: Las claves públicas (Stripe) están commiteadas directamente en un archivo `.env` en lugar de un `.env.example`.
  → **Solución recomendada**: Renombrar el archivo a `.env.example`, limpiar la clave real y añadir `.env` al `.gitignore`.

- **[🟡 IMPORTANTE] `backend-php/Dockerfile` y dependencias de Frontend**: El backend instala Node.js 20, tiene `vite.config.js` y `package.json`, e intenta hacer un `npm run build`. Al estar el frontend separado en React, esto solo engorda la imagen de Docker en producción.
  → **Solución recomendada**: Borrar `package.json` y `vite.config.js` de la carpeta backend, y quitar los pasos de Node/NPM en el `Dockerfile`.

- **[🟡 IMPORTANTE] `backend-php/app/Http/Controllers/Api/WebhookController.php`**: No valida de manera estricta que `N8N_WEBHOOK_URL` exista antes de hacer la petición HTTP. En producción sin n8n, podría registrar timeouts o fallos si no se controla adecuadamente.
  → **Solución recomendada**: Verificar si la variable de entorno existe y no está vacía antes de invocar a `Http::post`.

- **[🟢 MEJORA] `README.md`**: Menciona el proyecto como "LabStock Pro" en el título y descripción, cuando el branding real es "iStore Chile".
  → **Solución recomendada**: Actualizar los textos del README para que coincidan con la identidad del proyecto.

## 🚀 Infraestructura de Deploy

### Estado actual
- **Backend:** Intentado en Railway, pero probablemente caído o con errores de despliegue por fallos de CORS, base de datos no persistente o falta de créditos.
- **Frontend:** Desplegado exitosamente en Vercel, pero no se puede comunicar con el backend por el problema de CORS.

### Migración recomendada (Railway → Koyeb / Render)
Para estabilizar el backend de manera gratuita y eficiente:
1. **Actualizar `nginx.conf`**: Quitar todo rastro de CORS para que Laravel haga el trabajo.
2. **Actualizar `Dockerfile`**: Limpiar Node.js y pasos de Vite.
3. **Desplegar en Koyeb**:
   - Crear un Web Service usando GitHub.
   - Override command no es necesario (el `entrypoint.sh` arranca FPM y Nginx).
   - Koyeb inyectará dinámicamente el `$PORT` en `entrypoint.sh`.
4. **Variables a configurar en el Dashboard de Koyeb**:
   - `APP_KEY`, `APP_ENV=production`, `APP_URL=<koyeb-url>`
   - `DB_*` (Conectar a Neon.tech via Postgres).
   - `ALLOWED_ORIGINS=https://istore-chile.vercel.app`
   - Claves de Cloudinary, Gemini y Stripe.
5. **Frontend (Vercel)**:
   - Cambiar `VITE_API_URL` por la nueva URL de Koyeb.
6. **Stripe**:
   - Actualizar el endpoint del Webhook en el Dashboard de Stripe apuntando a `<koyeb-url>/api/webhooks/stripe`.

## 📋 Features Implementadas
- ✅ **Catálogo dinámico**: Productos paginados desde el backend.
- ✅ **Búsqueda optimizada**: `useDebounce` aplicado en React para evitar re-renders.
- ✅ **Carrito de compras**: Lógica de cliente/invitado unificada (sincronizable).
- ✅ **Checkout**: Flujo con Stripe habilitado (modo test).
- ✅ **Dashboard de Admin**: KPIs (ingresos, stock crítico, tendencias).
- ✅ **Asistente IA**: Gemini integrado para consultas de stock.
- ⚠️ **Webhooks**: Integración con Stripe lista, pero la comunicación con n8n requiere URL productiva.

## 🎯 Próximos Pasos Sugeridos
1. **Resolver el bloqueo de CORS**: Limpiar `nginx.conf` y asegurarse de que la URL del Vercel esté en el `.env` del backend bajo `ALLOWED_ORIGINS`.
2. **Sanear variables y repositorios**: Eliminar `.env` expuestos y el código residual de Node en el backend.
3. **Migrar infraestructura DB**: Asegurarse de tener Neon.tech como base productiva en lugar de SQLite, ejecutando migraciones completas.
4. **Optimizar Chatbot**: Prevenir el desbordamiento de tokens limitando los productos inyectados al prompt.
5. **Validar Webhooks en Producción**: Usar el CLI de Stripe para probar el flujo completo localmente o actualizar la URL remota.

## 📐 Reglas del Proyecto
1. Responde siempre en español.
2. Código conciso, sin comentarios obvios.
3. No instales dependencias de pago.
4. Siempre maneja errores (try/catch + JSON response).
5. No hardcodees secrets, usa .env.
6. Si hay un bug, muestra el fix directo.
7. Si falta contexto, pregunta ANTES de asumir.
8. Mobile-first en todo el frontend.
9. API responses: { success, data, error, message }.
10. Stripe webhooks siempre con validación de signature.
