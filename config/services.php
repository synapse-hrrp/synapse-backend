<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'pharma' => [
        'invoice_webhook_url'   => env('PHARMA_INVOICE_WEBHOOK_URL'),   // ex: http://127.0.0.1:8000/api/v1/finance/invoices
        'invoice_webhook_token' => env('PHARMA_INVOICE_WEBHOOK_TOKEN'), // Bearer <token>
    ],


    // 👉 Ajout de ta config HMAC
    'core' => [
        'secret' => null, // SECRET partagé avec l’orchestrateur
        'accept_event_versions' => [1],      // versions acceptées
    ],

];
