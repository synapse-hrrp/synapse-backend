<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\TrustHosts::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,

        // ➜ Supprime ces deux lignes si tes classes n'existent pas
        \App\Http\Middleware\SecurityHeaders::class,
        \App\Http\Middleware\ForceHttps::class,
    ];

    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            // ➜ supprime si la classe n'existe pas
            \App\Http\Middleware\SecurityHeaders::class,

            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    protected $routeMiddleware = [
        // Middleware Caisse (ignoré en L11 mais OK de les garder)
        'cashbox.open'    => \App\Http\Middleware\EnsureCashSessionOpen::class,
        'cashbox.service' => \App\Http\Middleware\EnsurePaymentServiceScope::class,

        // ✅ Seulement si la classe existe chez toi
        'service.access'  => \App\Http\Middleware\EnsureServiceAccess::class,
    ];
}
