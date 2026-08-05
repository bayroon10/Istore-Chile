# ============================================================
# Dockerfile de Producción — iStore Chile Backend
# Migrado de php:8.3-cli (dev) → php:8.3-fpm + Nginx
# ============================================================

# 1. Imagen base: PHP-FPM (proceso manager real para producción)
FROM php:8.3-fpm

# 2. Instalar dependencias del sistema y extensiones PHP
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    curl \
    nginx \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql zip gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 4. Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# 5. Carpeta de trabajo
WORKDIR /app
COPY . .

# 6. Dependencias PHP (sin dev)
RUN composer install --optimize-autoloader --no-dev --no-interaction

# 8. Permisos para Laravel
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# 9. Configuración Nginx
COPY nginx.conf /etc/nginx/sites-available/default

# 10. Entrypoint de producción
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# 11. Exponer puerto (Render lo manejará dinámicamente)
# EXPOSE 80 removido para no forzar el ruteo a 80

# 12. Arranque: migra + optimiza + FPM + Nginx
CMD ["/entrypoint.sh"]