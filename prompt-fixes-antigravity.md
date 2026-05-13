# 🛠️ TAREA — Aplicar fixes críticos en iStore Chile

## CONTEXTO
Soy Bairon Meneses. Proyecto: iStore Chile (repo: https://github.com/bayroon10/Istore-Chile).
Stack: Laravel 12 (PHP 8.3) en /backend-php + React 19 + Vite 7 en /frontend-react.
Frontend en Vercel: https://istore-chile.vercel.app
Backend en Railway: caído o con errores.

Ya tenemos un análisis completo. Necesito que apliques los siguientes fixes SIN romper
nada más. Lee cada archivo original antes de modificarlo.

---

## FIX 1 — CRÍTICO: Limpiar CORS de nginx.conf

**Archivo**: `backend-php/nginx.conf`

**Problema**: Tiene headers CORS manuales + `fastcgi_hide_header` que borra los de Laravel.
Esto provoca que el frontend en Vercel no pueda conectarse al backend ("SINCRONIZANDO...").

**Lo que debes hacer**:
- Leer el archivo actual
- Eliminar COMPLETAMENTE cualquier bloque con:
  - `add_header Access-Control-*`
  - `fastcgi_hide_header`
  - Variables `$cors_origin` o similares
  - Bloques `if ($request_method = 'OPTIONS')`
- Dejar solo la configuración base de Nginx para Laravel (location /, location ~ \.php$, etc.)
- El CORS lo maneja EXCLUSIVAMENTE `config/cors.php` de Laravel

**Verificar también**: `backend-php/config/cors.php`
- `allowed_origins` debe leer de `env('ALLOWED_ORIGINS')` o tener `['*']` en local
- Si no lee de env, actualízalo para que lo haga

---

## FIX 2 — CRÍTICO: Dockerfile sin Node.js

**Archivo**: `backend-php/Dockerfile`

**Problema**: Instala Node.js 20, corre `npm run build` y copia archivos de Vite.
El frontend está en Vercel — nada de esto es necesario en el backend.

**Lo que debes hacer**:
- Leer el Dockerfile actual completo
- Eliminar los pasos de Node.js, npm install y npm run build
- Mantener: PHP 8.3-fpm, extensiones PHP necesarias, Composer, permisos de storage
- El resultado debe ser una imagen PHP pura sin dependencias de frontend

**También eliminar del repo** (si existen en /backend-php):
- `package.json`
- `package-lock.json`  
- `vite.config.js`

---

## FIX 3 — CRÍTICO: .env expuesto en el repo

**Archivo**: `backend-php/.env` (si existe commiteado)

**Lo que debes hacer**:
- Verificar si `.env` está commiteado (no debe estarlo)
- Si existe: renombrarlo a `.env.example`, reemplazar todos los valores reales por placeholders vacíos
- Confirmar que `.gitignore` del backend incluye `.env`
- El `.env.example` debe tener estas variables (con valores vacíos):
  APP_NAME, APP_ENV, APP_KEY, APP_DEBUG, APP_URL,
  FRONTEND_URL, ALLOWED_ORIGINS,
  DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD,
  STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET,
  CLOUDINARY_URL, GEMINI_API_KEY, N8N_WEBHOOK_URL

---

## FIX 4 — IMPORTANTE: Limitar productos en ChatbotController

**Archivo**: `backend-php/app/Http/Controllers/Api/ChatbotController.php`

**Problema**: Hace `Product::get()` sin límite e inyecta TODO el catálogo al prompt de Gemini.
Con muchos productos causa Error 500 por límite de tokens.

**Lo que debes hacer**:
- Leer el controlador actual
- Cambiar la query de productos a:
  `Product::where('is_active', true)->where('stock', '>', 0)->with('category')->limit(20)->get([...campos necesarios...])`
- Mantener toda la lógica existente del prompt, solo limitar los productos

---

## FIX 5 — IMPORTANTE: WebhookController sin crash si falta N8N_WEBHOOK_URL

**Archivo**: `backend-php/app/Http/Controllers/Api/WebhookController.php` (o similar)

**Problema**: Llama a `Http::post($n8nUrl, [...])` sin verificar si la variable existe.
En producción sin n8n configurado, genera timeouts o excepciones.

**Lo que debes hacer**:
- Leer el archivo actual
- Encontrar la línea donde se llama Http::post hacia n8n
- Envolver con:
  ```php
  $n8nUrl = env('N8N_WEBHOOK_URL');
  if ($n8nUrl && trim($n8nUrl) !== '') {
      try {
          Http::timeout(5)->post($n8nUrl, [...datos...]);
      } catch (\Exception $e) {
          Log::error('[Webhook] Fallo n8n: ' . $e->getMessage());
          // No relanzar — el webhook de Stripe sigue funcionando
      }
  }
  ```

---

## FIX 6 — MEJORA: README correcto

**Archivo**: `README.md` (raíz del repo)

**Problema**: El README dice "LabStock Pro" siendo que el proyecto es "iStore Chile".

**Lo que debes hacer**:
- Reemplazar TODAS las menciones de "LabStock Pro" por "iStore Chile"
- Actualizar la descripción para que refleje el stack real:
  Laravel 12 + React 19 + Stripe + Gemini + Cloudinary + Neon.tech
- Agregar sección de setup local (backend: composer install + artisan, frontend: npm install)

---

## REGLAS PARA EJECUTAR ESTA TAREA

1. Lee SIEMPRE el archivo original antes de modificarlo
2. Modifica SOLO lo indicado — no cambies lógica de negocio ni estructura de rutas
3. Si un fix ya está aplicado (el archivo ya está correcto), dilo y pasa al siguiente
4. Por cada fix completado, confirma: "✅ FIX N aplicado — [descripción de lo que cambiaste]"
5. Al final, genera el commit message completo listo para copiar:
   ```
   fix: resolver CORS nginx, limpiar Dockerfile, limitar chatbot, sanitizar .env

   - nginx.conf: eliminar headers CORS manuales (manejado por Laravel cors.php)
   - Dockerfile: remover Node.js/Vite (frontend en Vercel)
   - ChatbotController: limitar productos a 20 para evitar overflow de tokens Gemini
   - WebhookController: validar N8N_WEBHOOK_URL antes de Http::post
   - .env: mover a .env.example con placeholders
   - README: actualizar nombre y stack del proyecto
   ```
6. NO hagas push automático — yo haré el commit y push manualmente después de revisar

---

## ORDEN DE PRIORIDAD
1. FIX 1 (nginx CORS) — el catálogo vuelve a cargar
2. FIX 3 (.env seguridad) — urgente
3. FIX 2 (Dockerfile) — antes del próximo deploy
4. FIX 4 (Chatbot) — evitar Error 500
5. FIX 5 (Webhook) — estabilidad
6. FIX 6 (README) — imagen del proyecto

Empieza por FIX 1. ¡Vamos!
