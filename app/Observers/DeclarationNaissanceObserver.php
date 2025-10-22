<?php

namespace App\Observers;

use App\Models\DeclarationNaissance;
use App\Services\InvoiceService;
use Illuminate\Validation\ValidationException;

class DeclarationNaissanceObserver
{
    public function creating(DeclarationNaissance $model): void
    {
        $code = 'DECL_NAIS';
        $tarif = app(InvoiceService::class)->findActiveTarif($model->service_slug, $code);

        if (!$tarif) {
            throw ValidationException::withMessages([
                'tarif' => "Tarif introuvable pour déclaration de naissance (code {$code})."
            ]);
        }
    }
}
