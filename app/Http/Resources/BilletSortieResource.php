<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BilletSortieResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'patient_id'            => $this->patient_id,
            'service_slug'          => $this->service_slug,
            'admission_id'          => $this->admission_id,

            'created_via'           => $this->created_via,
            'created_by_user_id'    => $this->created_by_user_id,

            'motif_sortie'          => $this->motif_sortie,
            'diagnostic_sortie'     => $this->diagnostic_sortie,
            'resume_clinique'       => $this->resume_clinique,
            'consignes'             => $this->consignes,
            'traitement_sortie'     => $this->traitement_sortie_json,
            'rdv_controle_at'       => optional($this->rdv_controle_at)->toISOString(),
            'destination'           => $this->destination,

            'statut'                => $this->statut,
            'remis_a'               => $this->remis_a,
            'signature_par'         => $this->signature_par,
            'date_signature'        => optional($this->date_signature)->toISOString(),
            'date_sortie_effective' => optional($this->date_sortie_effective)->toISOString(),

            // relations légères
            'patient'    => $this->whenLoaded('patient', fn() => [
                'id' => $this->patient->id,
                'nom' => $this->patient->nom ?? null,
                'prenom' => $this->patient->prenom ?? null,
            ]),
            'service'    => $this->whenLoaded('service', fn() => [
                'slug' => $this->service->slug,
                'nom'  => $this->service->nom ?? null,
            ]),
            'signataire' => $this->whenLoaded('signataire', fn() => [
                'id' => $this->signataire->id,
                'nom' => $this->signataire->nom ?? null,
            ]),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
            'deleted_at' => optional($this->deleted_at)->toISOString(),
        ];
    }
}
