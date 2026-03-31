# 1. Usamos la versión oficial de PHP
FROM php:8.3-cli

# 2. Instalamos las librerías del sistema para Postgres, ZIP y GD (Imágenes)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql zip gd

# 3. Instalamos Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Nos movemos a la carpeta de la app
WORKDIR /app

# 5. Copiamos todo tu código
COPY . .

# 6. Instalamos dependencias (ahora sí, con GD y ZIP listos)
RUN composer install --optimize-autoloader --no-dev

# 7. Exponemos el puerto
EXPOSE 10000

# 8. Encendemos el servidor
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT