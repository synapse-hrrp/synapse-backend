<?php

// app/Listeners/QueueVisiteSync.php
namespace App\Listeners;

use App\Events\VisiteCreated;
use App\Jobs\SyncVisiteToService;

class QueueVisiteSync
{
    public function handle(VisiteCreated $e): void
    {
        SyncVisiteToService::dispatch($e->visiteId);
    }
}
