<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Orígenes permitidos se leen desde la variable CORS_ALLOWED_ORIGINS
    | en el archivo .env. Separar múltiples dominios con coma.
    |
    | Ejemplo en .env:
    | CORS_ALLOWED_ORIGINS=https://mi-tienda.vercel.app,http://localhost:5173
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173'), 'https://istore-chile.vercel.app'],

    'allowed_origins_patterns' => [
        '#^https://labstock-pro-.*\.vercel\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 600,

    'supports_credentials' => true,

];
