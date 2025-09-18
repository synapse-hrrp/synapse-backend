<?php

namespace App\Observers;

use App\Models\Personnel;

class PersonnelObserver
{
    /**
     * Ne plus toucher à users.name.
     * On garde l'observer si plus tard tu veux logger, auditer, etc.
     */
    public function created(Personnel $p): void
    {
        // no-op
    }

    public function updated(Personnel $p): void
    {
        // no-op
    }
}
