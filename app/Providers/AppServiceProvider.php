<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Personnel;
use App\Models\Visite;
use App\Observers\VisiteObserver;
use App\Observers\PersonnelObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enregistre l'observer du modèle Personnel (si la classe existe)
        if (class_exists(PersonnelObserver::class)) {
            Personnel::observe(PersonnelObserver::class);
        }

        // Enregistre l'observer du modèle Visite (si la classe existe)
        if (class_exists(VisiteObserver::class)) {
            Visite::observe(VisiteObserver::class);
        }
    }
}
