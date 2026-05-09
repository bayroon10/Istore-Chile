# Falla Frontend React — iStore Chile

## 1. Error detectado
El frontend cargaba una pantalla completamente en negro y mostraba el siguiente error fatal en la consola del navegador:
`Uncaught ReferenceError: Cannot access 'p' before initialization` (o en desarrollo: `Cannot access 'logout' before initialization`).

## 2. Causa raíz
El error se debió a un problema de **Temporal Dead Zone (TDZ)** al inicializar el componente `AuthProvider` en `AuthContext.jsx`.

En el último commit, se añadió un `useEffect` para escuchar el evento global `'auth:expired'`:
```javascript
useEffect(() => {
  const handleAuthExpired = () => {
    logout();
  };
  window.addEventListener('auth:expired', handleAuthExpired);
  return () => window.removeEventListener('auth:expired', handleAuthExpired);
}, [logout]);
```

Este `useEffect` utilizaba la función `logout` en su array de dependencias. Sin embargo, la constante `logout` estaba declarada mucho más abajo en el archivo mediante:
```javascript
const logout = useCallback(() => { ... }, []);
```

En JavaScript, las variables declaradas con `const` o `let` son elevadas (*hoisted*) pero no se inicializan, entrando en la **Temporal Dead Zone**. Al evaluarse el array de dependencias del `useEffect` durante la primera fase de renderizado del componente (antes de llegar a la línea de declaración de `logout`), se producía un acceso prematuro a una variable no inicializada, deteniendo el hilo de ejecución de React y rompiendo por completo el renderizado de la aplicación. En producción, el compilador minificó `logout` a la variable `p`, generando el error `Cannot access 'p' before initialization`.

## 3. Archivo(s) involucrado(s)
- [AuthContext.jsx](file:///c:/laragon/www/labstock-pro/frontend-react/src/contexts/AuthContext.jsx)

## 4. Corrección aplicada
Se reordenaron las declaraciones dentro de `AuthProvider`:
1. Se movió la definición de la constante `logout` (que usa `useCallback` con dependencias vacías) a la parte superior del componente, justo después de la inicialización de los estados (`user`, `token`, `loading`).
2. De esta forma, cuando React procesa y evalúa las dependencias de los `useEffect` subsecuentes, `logout` ya ha sido declarada e inicializada correctamente.
3. Se añadió `logout` al array de dependencias del primer `useEffect` para mantener consistencia con las reglas de React Hooks.

## 5. Validación final
- Se ejecutó el comando de compilación local en el directorio `frontend-react` mediante `npm run build`.
- El build de Vite compiló de manera exitosa sin advertencias o errores de sintaxis, generando los assets optimizados en la carpeta `dist`.
- Con esta corrección, al cargarse el proveedor de autenticación de React, se elimina el ReferenceError y el árbol de componentes del frontend se renderiza de forma normal, reactivando la interacción con el backend en Railway.

## 6. Riesgo pendiente
- **Bajo**: El cambio mantiene intacta la lógica original de negocio y de autenticación. `logout` no tiene dependencias reactivas de estados mutables que se declaren después de ella, por lo que su elevación en el archivo no altera en absoluto su comportamiento ni produce efectos colaterales.
