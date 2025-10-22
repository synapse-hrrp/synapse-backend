<?php

namespace App\Observers;

use App\Models\Examen;
use App\Models\ServiceExamEvent;

class ExamenObserver
{
    public function created(Examen $examen): void
    {
        // Historique si créé depuis un service
        if ($examen->created_via === 'service' && $examen->service_slug) {
            ServiceExamEvent::create([
                'service_slug'   => $examen->service_slug,
                'examen_id'      => $examen->id,
                'action'         => 'created',
                'actor_user_id'  => $examen->created_by_user_id ?? $examen->demande_par,
                'meta'           => [
                    'code_examen' => $examen->code_examen,
                    'nom_examen'  => $examen->nom_examen,
                    'prix'        => $examen->prix,
                    'devise'      => $examen->devise,
                ],
            ]);
        }

        // Facturation automatique (si service actif)
       app(\App\Services\InvoiceService::class)->attachExam($examen);
    }
}
