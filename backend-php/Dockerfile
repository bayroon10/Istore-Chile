# 1. Usamos la versión oficial de PHP 8.3 con Apache para un entorno de producción real
FROM php:8.3-apache

# 2. Instalamos librerías del sistema y extensiones de PHP necesarias
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql zip gd

# 3. Habilitamos mod_rewrite para Laravel
RUN a2enmod rewrite

# 4. Configuramos el DocumentRoot de Apache a la carpeta /public de Laravel
ENV APACHE_DOCUMENT_ROOT /app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 5. Instalamos Node.js para compilar assets
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# 6. Instalamos Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 7. Carpeta de trabajo
WORKDIR /app

# 8. Copiamos los archivos y ajustamos permisos
COPY . .
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# 9. Instalamos dependencias de PHP y JS
RUN composer install --optimize-autoloader --no-dev
RUN npm install && npm run build

# 10. Exponemos el puerto (aunque Railway/Render usarán $PORT)
EXPOSE 80

# 11. Comando de inicio: 
# - Cambia el puerto de Apache dinámicamente al valor de $PORT
# - Ejecuta migraciones
# - Arranca Apache
CMD sed -i "s/Listen 80/Listen ${PORT:-80}/g" /etc/apache2/ports.conf && \
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT:-80}>/g" /etc/apache2/sites-available/000-default.conf && \
    php artisan migrate --force && \
    apache2-foreground

