<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Mets ici EXACTEMENT les origines qui appelleront ton API
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        // ajoute l’URL réelle de ton front en prod :
        // 'https://mon-front-next.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // ✅ En mode token Bearer, on laisse à false
    'supports_credentials' => false,
];
