<?php

namespace App\Services\Contracts;
use App\Models\Visite;

interface ServiceAdapter {
    public function handleVisit(Visite $visit, array $payload): void;
}
