<?php

// app/Services/ServiceRegistry.php
namespace App\Services;

use App\Models\Service;
use App\Services\Contracts\ServiceAdapter;

class ServiceRegistry
{
    public function for(Service $service): ServiceAdapter
    {
        return app(\App\Services\Adapters\ConfigDrivenAdapter::class);
    }
}