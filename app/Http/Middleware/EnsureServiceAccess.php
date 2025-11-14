<?php
// app/Http/Middleware/EnsureServiceAccess.php
namespace App\Http\Middleware;

use App\Models\Service;
use App\Support\ServiceAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnsureServiceAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) abort(401);

        /** @var ServiceAccess $access */
        $access = app(ServiceAccess::class);

        // Déterminer le service ciblé : route {service} (objet/slug/id) ou ?service_id=...
        $serviceId = $request->route('service');

        if ($serviceId instanceof Service) {
            $serviceId = $serviceId->id;
        } elseif (is_numeric($serviceId)) {
            $serviceId = (int) $serviceId;
        } elseif ($request->filled('service_id')) {
            $serviceId = (int) $request->input('service_id');
        } else {
            $serviceId = null;
        }

        // Global => OK direct
        if ($access->isGlobal($user)) {
            return $next($request);
        }

        // Pas global => service requis et autorisé
        if ($serviceId === null) {
            throw ValidationException::withMessages([
                'service' => "Service requis pour un utilisateur sans accès global.",
            ]);
        }

        $allowed = $access->allowedServiceIds($user);
        if (! in_array((int)$serviceId, array_map('intval', $allowed), true)) {
            throw ValidationException::withMessages([
                'service' => "Service non autorisé pour cet utilisateur.",
            ]);
        }

        // dispo pour les contrôleurs si besoin
        $request->attributes->set('service_id_checked', (int) $serviceId);

        return $next($request);
    }
}
