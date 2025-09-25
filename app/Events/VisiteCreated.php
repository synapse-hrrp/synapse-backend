<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VisiteCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $visiteId,
        public ?int   $actorUserId = null
    ) {}
}
