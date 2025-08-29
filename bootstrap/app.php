<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Groupe API
        $middleware->group('api', [
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // Alias globaux (incl. Spatie)
        $middleware->alias([
            'auth'               => \App\Http\Middleware\Authenticate::class,
            'auth.basic'         => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'cache.headers'      => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can'                => \Illuminate\Auth\Middleware\Authorize::class,
            'guest'              => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'password.confirm'   => \Illuminate\Auth\Middleware\RequirePassword::class,
            'signed'             => \Illuminate\Routing\Middleware\ValidateSignature::class,
            'throttle'           => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified'           => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

            // Spatie\Permission
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,

            // Sanctum abilities
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability'   => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,

        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withProviders([
        // Uniquement ceux qui existent réellement
        App\Providers\RouteServiceProvider::class,
    ])
    ->create();
