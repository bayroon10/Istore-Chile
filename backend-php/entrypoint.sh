#!/bin/sh
# =============================================================
# Entrypoint de producción — iStore Chile Backend
# Reemplaza el inseguro "php artisan serve"
# =============================================================
set -e

echo "[iStore] Iniciando secuencia de arranque de producción..."

# 1. Esperar que la base de datos esté disponible (opcional, configurable)
# Si Neon.tech no responde en 30s, fallamos rápido
echo "[iStore] Verificando conexión a base de datos..."
php artisan db:monitor --max=3 2>/dev/null || echo "[iStore] Advertencia: db:monitor no disponible, continuando..."

# 2. Ejecutar migraciones (--force para producción)
echo "[iStore] Ejecutando migraciones..."
php artisan migrate --force || echo "[iStore] ADVERTENCIA: No se pudieron ejecutar las migraciones. Continuando inicio del servidor..."

# 3. Limpiar caches antiguos antes de optimizar
echo "[iStore] Limpiando caches antiguos..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

# 4. Optimizar la aplicación para producción
echo "[iStore] Optimizando cache de configuración y rutas..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Limpiar cache de optimización si es necesario
php artisan storage:link --force 2>/dev/null || true

echo "[iStore] Iniciando PHP-FPM..."
php-fpm -D

echo "[iStore] Iniciando Nginx..."
exec nginx -g 'daemon off;'
