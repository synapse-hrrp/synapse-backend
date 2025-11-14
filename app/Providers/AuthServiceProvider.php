<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Support\ServiceAccess;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Les politiques du modèle.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Exemple : 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Enregistrement de toutes les autorisations.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        
        // ✅ Gate : accès global (admin ou token global)
        Gate::define('service.global', function ($user) {
            return app(ServiceAccess::class)->isGlobal($user);
        });

        // ✅ Gate : vérifie si l'utilisateur a accès à un service donné
        Gate::define('service.in', function ($user, int $serviceId) {
            $access = app(ServiceAccess::class);
            if ($access->isGlobal($user)) return true;
            return in_array((int)$serviceId, array_map('intval', $access->allowedServiceIds($user)), true);
        });
    }
}
