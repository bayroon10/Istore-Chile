# 1. Usamos la versión CLI pura (Sin Apache, cero conflictos)
FROM php:8.3-cli

# 2. Instalamos las extensiones necesarias
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

# 3. Instalamos Node.js (Para Vite/React)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# 4. Instalamos Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Carpeta de trabajo
WORKDIR /app
COPY . .

# 6. Instalamos dependencias y compilamos
RUN composer install --optimize-autoloader --no-dev
RUN npm install && npm run build

# 7. Permisos para Laravel
RUN chmod -R 775 /app/storage /app/bootstrap/cache

# 8. EL ARRANQUE MAESTRO: Migramos y prendemos el servidor nativo de Laravel
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}