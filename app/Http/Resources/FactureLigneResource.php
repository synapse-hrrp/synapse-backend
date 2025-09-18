<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FactureLigneResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'designation'   => $this->designation,
            'quantite'      => (string) $this->quantite,
            'prix_unitaire' => (string) $this->prix_unitaire,
            'montant'       => (string) $this->montant,
            'tarif_id'      => $this->tarif_id,
        ];
    }
}
