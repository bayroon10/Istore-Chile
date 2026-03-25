# Usamos la versión oficial de PHP 8.3
FROM php:8.3-cli

# Instalamos las herramientas para conectar con PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql

# Instalamos Composer (El gestor de paquetes de PHP)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Nos movemos a la carpeta de la app
WORKDIR /app

# Copiamos todo tu código a la nube
COPY . .

# Instalamos las dependencias de Laravel
RUN composer install --optimize-autoloader --no-dev

# Exponemos el puerto de Render
EXPOSE 10000

# Encendemos el servidor y migramos la base de datos
# Encendemos el servidor, migramos la base de datos e inyectamos el Admin
CMD php artisan migrate --force && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=$PORT