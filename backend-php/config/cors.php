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

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:3000')),

    'allowed_origins_patterns' => [
        '#^https://labstock-pro-.*\.vercel\.app$#',
    ],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'Origin',
        'X-Session-Id',
    ],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 600,

    'supports_credentials' => true,

];
