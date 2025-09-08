<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PansementResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'patient_id'        => $this->patient_id,
            'visite_id'         => $this->visite_id,
            'soignant_id'       => $this->soignant_id,
            'soignant_name'     => optional($this->soignant)->name,

            'type'              => $this->type,
            'date_soin'         => optional($this->date_soin)->toISOString(),
            'status'            => $this->status,
            'observation'       => $this->observation,
            'etat_plaque'       => $this->etat_plaque,
            'produits_utilises' => $this->produits_utilises,

            // Relations
            'patient'  => $this->whenLoaded('patient'),
            'visite'   => $this->whenLoaded('visite'),
            'soignant' => $this->whenLoaded('soignant', function () {
                return [
                    'id'    => $this->soignant->id,
                    'name'  => $this->soignant->name,
                    'email' => $this->soignant->email,
                ];
            }),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
