<?php

$production = env('APP_ENV') === 'production' || env('APP_DEBUG') === false;

// Allow only the frontend site in production (include punycode variants)
$allowedOrigins = $production
    ? [
        'https://vi-santé.com',
        'https://www.vi-santé.com',
        'https://xn--vi-sant-hya.com',
        'https://www.xn--vi-sant-hya.com',
        'https://api.vi-santé.com',
        'https://api.xn--vi-sant-hya.com',
    ]
    : [
        'http://localhost:3000',
        'http://localhost:5173',
        'http://127.0.0.1:8000',
        'http://localhost:8000',
        'https://vi-santé.com',
        'https://www.vi-santé.com',
        'https://xn--vi-sant-hya.com',
        'https://www.xn--vi-sant-hya.com',
        'https://api.vi-santé.com',
        'https://api.xn--vi-sant-hya.com',
    ];

$allowedMethods = $production
    ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
    : ['*'];

$allowedHeaders = $production
    ? ['Content-Type', 'X-Requested-With', 'Authorization', 'Accept', 'Origin', 'Cache-Control', 'Pragma']
    : ['*'];

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => $allowedMethods,

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => $allowedHeaders,

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
