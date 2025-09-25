<?php
// app/Listeners/SendVisiteToServices.php

namespace App\Listeners;

use App\Events\VisiteCreated;
use App\Jobs\SyncVisiteToService;

class SendVisiteToServices
{
    public function handle(VisiteCreated $event): void
    {
        // On délègue au Job (queue) : robuste, retryable
        SyncVisiteToService::dispatch($event->visiteId);
    }
}
