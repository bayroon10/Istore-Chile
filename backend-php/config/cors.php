<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_unique(array_merge([
        'https://istore-chile.vercel.app',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ], explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))), static fn (string $origin): bool => $origin !== '' && ! str_contains($origin, '*'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Idempotency-Key',
        'X-Requested-With',
        'X-Session-Id',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
