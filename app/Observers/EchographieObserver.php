<?php

namespace App\Observers;

use App\Models\Echographie;
use App\Services\InvoiceService;
use Illuminate\Validation\ValidationException;

class EchographieObserver
{
    public function creating(Echographie $model): void
    {
        $code = $model->code_echo ? strtoupper(trim($model->code_echo)) : '';
        if (!$code) {
            throw ValidationException::withMessages([
                'tarif_code' => 'Code tarif écho manquant.'
            ]);
        }

        $tarif = app(InvoiceService::class)->findActiveTarif($model->service_slug, $code);
        if (!$tarif) {
            throw ValidationException::withMessages([
                'tarif_code' => "Tarif introuvable pour code '{$code}'"
            ]);
        }

        // On ne crée pas la facture ici: c'est fait après création via services dédiés si besoin
    }
}
