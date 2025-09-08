<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PediatrieResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'           => $this->id,
            'patient_id'   => $this->patient_id,
            'visite_id'    => $this->visite_id,
            'soignant_id'  => $this->soignant_id,

            'date_acte'    => optional($this->date_acte)->toISOString(),
            'motif'        => $this->motif,
            'diagnostic'   => $this->diagnostic,

            'poids'                 => $this->poids !== null ? (float)$this->poids : null,
            'taille'                => $this->taille !== null ? (float)$this->taille : null,
            'temperature'           => $this->temperature !== null ? (float)$this->temperature : null,
            'perimetre_cranien'     => $this->perimetre_cranien !== null ? (float)$this->perimetre_cranien : null,
            'saturation'            => $this->saturation,
            'frequence_cardiaque'   => $this->frequence_cardiaque,
            'frequence_respiratoire'=> $this->frequence_respiratoire,

            'examen_clinique' => $this->examen_clinique,
            'traitements'     => $this->traitements,
            'observation'     => $this->observation,
            'statut'          => $this->statut,

            // Relations chargées
            'patient'  => $this->whenLoaded('patient'),
            'visite'   => $this->whenLoaded('visite'),
            'soignant' => $this->whenLoaded('soignant', fn() => [
                'id'    => $this->soignant->id,
                'name'  => $this->soignant->name,
                'email' => $this->soignant->email,
            ]),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
