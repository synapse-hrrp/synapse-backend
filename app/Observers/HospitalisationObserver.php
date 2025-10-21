<?php

namespace App\Observers;

use App\Models\Hospitalisation;
use App\Services\InvoiceService;
use Illuminate\Validation\ValidationException;

class HospitalisationObserver
{
    public function creating(Hospitalisation $model): void
    {
        $code = 'HOSP_ADM';
        $tarif = app(InvoiceService::class)->findActiveTarif($model->service_slug, $code);

        if (!$tarif) {
            throw ValidationException::withMessages([
                'tarif' => "Tarif introuvable pour hospitalisation (code {$code})."
            ]);
        }
    }
}
