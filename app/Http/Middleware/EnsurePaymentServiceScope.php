<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePaymentServiceScope
{
    public function handle(Request $request, Closure $next)
    {
        $user    = $request->user();
        $session = $request->attributes->get('cash_session'); // injecté par EnsureCashSessionOpen

        // 1) Service candidat : priorité à l’input, sinon celui de la session
        $serviceId = $request->input('service_id');

        if ($serviceId === null && $session) {
            $serviceId = $session->service_id; // peut rester null → session “générale”
        }

        // On normalise : int ou null
        if ($serviceId !== null && $serviceId !== '') {
            $serviceId = (int) $serviceId;
        } else {
            $serviceId = null;
        }

        // 2) On N’APPLIQUE PLUS de restriction ici :
        //    - plus de "L'utilisateur n'a pas d'accès global : un service valide est requis."
        //    - plus de "Service non autorisé..."
        //
        // Toute la logique d’autorisation est maintenant dans ReglementController::store()

        // On passe juste l’info au contrôleur
        $request->attributes->set('payment_service_id', $serviceId);

        return $next($request);
    }
}
