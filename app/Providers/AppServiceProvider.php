<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Modèles existants
use App\Models\Personnel;
use App\Models\Visite;
use App\Models\Examen;

// Nouveaux modèles
use App\Models\Echographie;
use App\Models\Hospitalisation;
use App\Models\DeclarationNaissance;
use App\Models\BilletSortie;
use App\Models\Accouchement;

// Observers existants
use App\Observers\VisiteObserver;
use App\Observers\PersonnelObserver;
use App\Observers\ExamenObserver;

// Nouveaux observers
use App\Observers\EchographieObserver;
use App\Observers\HospitalisationObserver;
use App\Observers\DeclarationNaissanceObserver;
use App\Observers\BilletSortieObserver;
use App\Observers\AccouchementObserver;

// Observers Pharma
use App\Models\Pharmacie\PharmaArticle;
use App\Models\Pharmacie\Dci;
use App\Observers\PharmaArticleObserver;
use App\Observers\DciObserver;

// ✅ Ajout: binding ServiceAccess
use App\Support\ServiceAccess;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ✅ Singleton ServiceAccess (ajouté)
        $this->app->singleton(ServiceAccess::class, fn() => new ServiceAccess());
    }

    public function boot(): void
    {
        // Observers de base
        if (class_exists(PersonnelObserver::class)) {
            Personnel::observe(PersonnelObserver::class);
        }

        if (class_exists(VisiteObserver::class)) {
            Visite::observe(VisiteObserver::class);
        }

        if (class_exists(ExamenObserver::class)) {
            Examen::observe(ExamenObserver::class);
        }

        // Observers Pharma
        PharmaArticle::observe(PharmaArticleObserver::class);
        Dci::observe(DciObserver::class);

        // Nouveaux observers
        if (class_exists(EchographieObserver::class)) {
            Echographie::observe(EchographieObserver::class);
        }

        if (class_exists(HospitalisationObserver::class)) {
            Hospitalisation::observe(HospitalisationObserver::class);
        }

        if (class_exists(DeclarationNaissanceObserver::class)) {
            DeclarationNaissance::observe(DeclarationNaissanceObserver::class);
        }

        if (class_exists(BilletSortieObserver::class)) {
            BilletSortie::observe(BilletSortieObserver::class);
        }

        if (class_exists(AccouchementObserver::class)) {
            Accouchement::observe(AccouchementObserver::class);
        }
    }
}
