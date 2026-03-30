# 1. Usamos una imagen profesional optimizada para Laravel en Producción
FROM serversideup/php:8.3-cli

# 2. Nos movemos a la carpeta de la app
WORKDIR /var/www/html

# 3. Copiamos todo tu código a la nube
COPY . .

# 4. Instalamos las dependencias de Laravel (ahora sí, con todas las extensiones del sistema listas)
RUN composer install --optimize-autoloader --no-dev

# 5. Exponemos el puerto de Render
EXPOSE 10000

# 6. Encendemos el servidor y migramos
CMD php artisan migrate --force && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=$PORT