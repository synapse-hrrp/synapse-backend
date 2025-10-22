<?php

namespace App\Observers;

use App\Models\BilletSortie;
use App\Services\InvoiceService;
use Illuminate\Validation\ValidationException;

class BilletSortieObserver
{
    public function creating(BilletSortie $model): void
    {
        $code = 'BIL_SORTIE';
        $tarif = app(InvoiceService::class)->findActiveTarif($model->service_slug, $code);

        if (!$tarif) {
            throw ValidationException::withMessages([
                'tarif' => "Tarif introuvable pour billet de sortie (code {$code})."
            ]);
        }
    }
}
