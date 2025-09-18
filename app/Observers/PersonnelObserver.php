<?php

namespace App\Observers;

use App\Models\Personnel;

class PersonnelObserver
{
    public function created(Personnel $p): void
    {
        $this->syncUserName($p);
    }

    public function updated(Personnel $p): void
    {
        if ($p->wasChanged(['first_name','last_name'])) {
            $this->syncUserName($p);
        }
    }

    private function syncUserName(Personnel $p): void
    {
        $user = $p->user()->first();
        if (! $user) {
            return;
        }

        $newName = $p->full_name ?: $user->email; // fallback = email
        if ($newName && $newName !== $user->name) {
            $user->forceFill(['name' => $newName])->saveQuietly();
        }
    }
}
