<?php

namespace App\Http\Middleware;

use App\Models\Caisse\CashRegisterSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnsureCashSessionOpen
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $workstation = $request->header('X-Workstation');
        if (! $workstation) {
            throw ValidationException::withMessages([
                'workstation' => 'Le header X-Workstation est requis.',
            ]);
        }

        $session = CashRegisterSession::query()
            ->where('user_id', $user->id)
            ->where('workstation', $workstation)
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'session' => 'Aucune session de caisse ouverte pour cet utilisateur sur ce poste.',
            ]);
        }

        // Rendez la session dispo aux contrôleurs
        $request->attributes->set('cash_session', $session);

        return $next($request);
    }
}
