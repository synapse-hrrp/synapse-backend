<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Personnel;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (class_exists(\App\Observers\PersonnelObserver::class)) {
            Personnel::observe(\App\Observers\PersonnelObserver::class);
        }
    }
}
