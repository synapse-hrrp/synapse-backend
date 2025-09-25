<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GynecologieResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'patient_id'    => $this->patient_id,
            'visite_id'     => $this->visite_id,
            'soignant_id'   => $this->soignant_id,

            'date_acte'     => optional($this->date_acte)->toISOString(),
            'motif'         => $this->motif,
            'diagnostic'    => $this->diagnostic,
            'examen_clinique' => $this->examen_clinique,
            'traitements'   => $this->traitements,
            'observation'   => $this->observation,

            'tension_arterielle'     => $this->tension_arterielle,
            'temperature'            => $this->temperature !== null ? (float) $this->temperature : null,
            'frequence_cardiaque'    => $this->frequence_cardiaque !== null ? (int) $this->frequence_cardiaque : null,
            'frequence_respiratoire' => $this->frequence_respiratoire !== null ? (int) $this->frequence_respiratoire : null,

            'statut' => $this->statut,

            // Relations
            'patient'  => $this->whenLoaded('patient'),
            'visite'   => $this->whenLoaded('visite'),
            'soignant' => $this->whenLoaded('soignant', fn() => $this->soignant ? [
                'id'    => $this->soignant->id,
                'name'  => $this->soignant->name,
                'email' => $this->soignant->email,
            ] : null),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
            'deleted_at' => optional($this->deleted_at)->toISOString(),
        ];
    }
}
