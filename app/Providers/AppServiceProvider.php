<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Modèles déjà présents
use App\Models\Personnel;
use App\Models\Visite;
use App\Models\Examen;

// ➕ Nouveaux modèles
use App\Models\Echographie;
use App\Models\Hospitalisation;
use App\Models\DeclarationNaissance;
use App\Models\BilletSortie;
use App\Models\Accouchement;                 // <— AJOUT

// Observers existants
use App\Observers\VisiteObserver;
use App\Observers\PersonnelObserver;
use App\Observers\ExamenObserver;

// ➕ Nouveaux observers
use App\Observers\EchographieObserver;
use App\Observers\HospitalisationObserver;
use App\Observers\DeclarationNaissanceObserver;
use App\Observers\BilletSortieObserver;
use App\Observers\AccouchementObserver;      // <— AJOUT

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // — Observers existants (avec sécurité sur la classe)
        if (class_exists(PersonnelObserver::class)) {
            Personnel::observe(PersonnelObserver::class);
        }

        if (class_exists(VisiteObserver::class)) {
            Visite::observe(VisiteObserver::class);
        }

        if (class_exists(ExamenObserver::class)) {
            Examen::observe(ExamenObserver::class);
        }

        // — Nouveaux observers
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

        // — Accouchement
        if (class_exists(AccouchementObserver::class)) {
            Accouchement::observe(AccouchementObserver::class);
        }
    }
}
