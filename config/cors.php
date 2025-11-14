<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Ajoute TOUTES les origines de ton front (dev & prod éventuelle)
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://192.168.1.176:3000',  // ton Next.js sur le LAN
        // 'https://ton-front-prod.tld', // si tu as une prod en HTTPS
    ],

    // (Option pratique si l’IP change sur le LAN)
    'allowed_origins_patterns' => [
        // '/^http:\/\/192\.168\.1\.\d+:3000$/',
    ],

    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept',
        'Origin',
        'X-Requested-With',
        // 👇 indispensables pour ton use-case Caisse
        'Idempotency-Key',
        'X-Workstation',
    ],

    // (expose si tu comptes lire ces headers côté front, sinon facultatif)
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'Retry-After',
    ],

    'max_age' => 86400,

    // Bearer tokens (pas de cookies) → false
    'supports_credentials' => false,
];
