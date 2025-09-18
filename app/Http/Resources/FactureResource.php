<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FactureResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'numero'        => $this->numero,
            'statut'        => $this->statut,
            'montant_total' => (string) $this->montant_total,
            'montant_du'    => (string) $this->montant_du,
            'devise'        => $this->devise,
            'visite_id'     => $this->visite_id,
            'patient_id'    => $this->patient_id,

            // Relations si chargées
            'lignes'     => FactureLigneResource::collection($this->whenLoaded('lignes')),
            'reglements' => ReglementResource::collection($this->whenLoaded('reglements')),

            // Liens pratiques
            'links' => [
                'self' => route('factures.show', $this->resource, false),
                'pdf'  => route('factures.pdf',  $this->resource, false),
            ],
        ];
    }
}
