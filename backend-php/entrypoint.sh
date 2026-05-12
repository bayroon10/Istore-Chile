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
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan optimize:clear || true

# 4. Optimizar la aplicación para producción
echo "[iStore] Optimizando cache de configuración y rutas..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# 4. Limpiar cache de optimización si es necesario
php artisan storage:link --force 2>/dev/null || true

echo "[iStore] Iniciando PHP-FPM..."
php-fpm -D

# Dynamic PORT binding for Railway compatibility
PORT_TO_LISTEN=${PORT:-8080}
echo "[iStore] Configurando Nginx para escuchar en el puerto ${PORT_TO_LISTEN}..."
sed -i "s/PORT_PLACEHOLDER/$PORT/g" /etc/nginx/sites-available/default
if [ ! -L /etc/nginx/sites-enabled/default ]; then
    ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
fi

echo "[iStore] Probando configuración de Nginx..."
nginx -t || echo "[iStore] ADVERTENCIA: Falló la validación de configuración de Nginx."

echo "[iStore] Iniciando Nginx..."
exec nginx -g 'daemon off;'

