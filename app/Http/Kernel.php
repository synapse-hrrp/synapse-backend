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

        // ⬇️ Ajouts sécurité
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
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\ThrottleRequests::class . ':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

    ];

    protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
    {
        $schedule->call(function () {
            \Laravel\Sanctum\PersonalAccessToken::query()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->delete();
        })->daily()->name('purge-expired-sanctum-tokens');
    }

}
