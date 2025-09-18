<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        /**
         * Limiteur global API appliqué au groupe middleware "api"
         * (via Kernel: throttle:api). Clé = user_id ou IP.
         */
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)
                ->by($request->user()?->id ?: $request->ip());
        });

        /**
         * Limiteur pour les actions authentifiées (routes protégées).
         * On le pose explicitement sur nos blocs avec ->middleware('throttle:auth').
         */
        RateLimiter::for('auth', function (Request $request) {
            // 60 requêtes/min par utilisateur connecté (fallback: IP)
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });

        /**
         * Limiteur de LOGIN anti brute-force.
         * Double barrière :
         *  - 5 tentatives/min par couple (email + IP)
         *  - 50 tentatives/heure par IP
         * + réponse JSON claire quand seuil dépassé.
         */
        RateLimiter::for('login', function (Request $request) {
            $emailKey = mb_strtolower((string) $request->input('email'));
            $ip       = $request->ip();

            return [
                Limit::perMinute(5)
                    ->by('login|'.$emailKey.'|'.$ip)
                    ->response(function () {
                        return response()->json([
                            'message' => 'Trop de tentatives de connexion. Réessaie dans une minute.',
                            'retry_after_seconds' => 60,
                        ], 429);
                    }),

                Limit::perHour(50)
                    ->by('login-ip|'.$ip)
                    ->response(function () {
                        return response()->json([
                            'message' => 'Trop de tentatives depuis cette adresse IP. Réessaie plus tard.',
                            'retry_after_seconds' => 3600,
                        ], 429);
                    }),
            ];
        });

        /**
         * (Optionnel) Limiteur plus strict pour la caisse/paiement.
         * Utilise-le sur POST /finance/payments si tu veux : ->middleware('throttle:payments')
         */
        RateLimiter::for('payments', function (Request $request) {
            return Limit::perMinute(10)->by(($request->user()?->id ?: 'guest')."|".$request->ip());
        });
    }
}
