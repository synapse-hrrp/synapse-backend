<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\Personnel;
use App\Models\Visite;
use App\Observers\VisiteObserver;
use App\Observers\PersonnelObserver;

use App\Models\Examen;
use App\Observers\ExamenObserver;

use App\Models\Pharmacie\PharmaArticle;
use App\Models\Pharmacie\Dci;
use App\Observers\PharmaArticleObserver;
use App\Observers\DciObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (class_exists(PersonnelObserver::class)) {
            Personnel::observe(PersonnelObserver::class);
        }

        if (class_exists(VisiteObserver::class)) {
            Visite::observe(VisiteObserver::class);
        }

        Examen::observe(ExamenObserver::class);

        PharmaArticle::observe(PharmaArticleObserver::class);
        Dci::observe(DciObserver::class); // optionnel mais recommandé
    }
}
