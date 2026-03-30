# 1. Usamos la imagen oficial optimizada
FROM serversideup/php:8.3-cli

# 2. Copiamos nuestro código a la carpeta estándar del servidor web
COPY . /var/www/html

# 3. Instalamos dependencias
RUN composer install --optimize-autoloader --no-dev

# 4. Exponemos el puerto de Render
EXPOSE 10000

# 5. Encendemos el servidor y migramos
CMD php artisan migrate --force && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=$PORT