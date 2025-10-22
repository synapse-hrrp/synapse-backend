<?php

namespace App\Observers;

use App\Models\Accouchement;
use App\Services\InvoiceService;
use Illuminate\Validation\ValidationException;

class AccouchementObserver
{
    public function creating(Accouchement $model): void
    {
        $code  = config('billing.codes.accouchement', 'ACCOUCH');
        $tarif = app(InvoiceService::class)->findActiveTarif($model->service_slug, $code);

        if (!$tarif) {
            throw ValidationException::withMessages([
                'tarif' => "Tarif introuvable pour accouchement (code {$code})."
            ]);
        }
    }
}
