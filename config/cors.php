<?php

return [

    // On ne sert que l’API (pas besoin de sanctum/csrf-cookie en mode Bearer)
    'paths' => ['api/*'],

    // Méthodes permises par le front
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // ✅ Liste BLANCHE des origines (mets tes domaines exacts)
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
        'http://127.0.0.1:3000',
        // 'https://ton-front-prod.com',
    ],

    // On n’utilise pas les patterns quand on a une whitelist stricte
    'allowed_origins_patterns' => [],

    // En mode Bearer, autorise les en-têtes classiques + Authorization
    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept',
        'Origin',
        'X-Requested-With',
    ],

    // Expose quelques headers utiles aux clients (facultatif)
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'Retry-After',
    ],

    // Cache des pré-requêtes OPTIONS (24h)
    'max_age' => 86400,

    // ⚠️ Bearer tokens → PAS de cookies → PAS de credentials
    'supports_credentials' => false,
];
