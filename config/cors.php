<?php

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

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:8000',
        'https://vi-santé.com',
        'https://api.vi-santé.com',
        'https://xn--vi-sant-hya.com',
        'https://api.xn--vi-sant-hya.com',
        'https://vi-santé.com/api/medecins',
        'https://vi-santé.com/api/appointments',
        'https://vi-santé.com/api/organisations',
        'https://api.vi-sant-hya.com/api/medecins',
        'https://api.vi-sant-hya.com/api/appointments',
        'https://api.vi-sant-hya.com/api/organisations',
        'https://api.vi-sant-hya.com/api/check-availability',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        '*',
        'Authorization',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
