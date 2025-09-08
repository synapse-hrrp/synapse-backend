<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SmiResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                      => $this->id,
            'patient_id'              => $this->patient_id,
            'visite_id'               => $this->visite_id,
            'soignant_id'             => $this->soignant_id,
            'date_acte'               => optional($this->date_acte)->toJSON(),
            'motif'                   => $this->motif,
            'diagnostic'              => $this->diagnostic,
            'examen_clinique'         => $this->examen_clinique,
            'traitements'             => $this->traitements,
            'observation'             => $this->observation,
            'tension_arterielle'      => $this->tension_arterielle,
            'temperature'             => is_null($this->temperature) ? null : (float) $this->temperature,
            'frequence_cardiaque'     => $this->frequence_cardiaque,
            'frequence_respiratoire'  => $this->frequence_respiratoire,
            'statut'                  => $this->statut,

            'patient'  => new \App\Http\Resources\PatientResource($this->whenLoaded('patient')),
            'visite'   => $this->whenLoaded('visite'),
            'soignant' => $this->whenLoaded('soignant', function () {
                return [
                    'id'    => $this->soignant->id,
                    'name'  => $this->soignant->name,
                    'email' => $this->soignant->email,
                ];
            }),

            'created_at' => optional($this->created_at)->toJSON(),
            'updated_at' => optional($this->updated_at)->toJSON(),
            'deleted_at' => optional($this->deleted_at)->toJSON(),
        ];
    }
}
