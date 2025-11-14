<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Où rediriger l'utilisateur non authentifié.
     */
    protected function redirectTo($request): ?string
    {
        // Pour les appels API (JSON) => pas de redirection, on laisse Laravel renvoyer 401
        if ($request->expectsJson()) {
            return null;
        }

        // Pour une requête navigateur classique (HTML) => on renvoie l'URL brute du frontend
        return '/login'; // 🔹 Pas besoin de route('login')
    }
}
