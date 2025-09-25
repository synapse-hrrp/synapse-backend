<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SmiResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                     => $this->id,
            'patient_id'             => $this->patient_id,
            'visite_id'              => $this->visite_id,
            'soignant_id'            => $this->soignant_id,

            'date_acte'              => optional($this->date_acte)->toISOString(),
            'motif'                  => $this->motif,
            'diagnostic'             => $this->diagnostic,
            'examen_clinique'        => $this->examen_clinique,
            'traitements'            => $this->traitements,
            'observation'            => $this->observation,

            'tension_arterielle'     => $this->tension_arterielle,
            'temperature'            => is_null($this->temperature) ? null : (float) $this->temperature,
            'frequence_cardiaque'    => $this->frequence_cardiaque,
            'frequence_respiratoire' => $this->frequence_respiratoire,
            'statut'                 => $this->statut,

            // Relations
            'patient'  => new \App\Http\Resources\PatientResource($this->whenLoaded('patient')),
            'visite'   => $this->whenLoaded('visite'),
            'soignant' => $this->whenLoaded('soignant', function () {
                // Ici soignant = Personnel (full_name, job_title, ...).
                // Email via soignant->user si la relation existe/est chargée.
                return [
                    'id'        => $this->soignant->id,
                    'full_name' => $this->soignant->full_name ?? trim(($this->soignant->first_name ?? '').' '.($this->soignant->last_name ?? '')),
                    'job_title' => $this->soignant->job_title ?? null,
                    'email'     => optional($this->soignant->user)->email, // nécessite éventuel eager-load de user
                ];
            }),
            // Si tu as ajouté service_id + relation:
            'service'  => $this->whenLoaded('service'),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
            'deleted_at' => optional($this->deleted_at)->toISOString(),
        ];
    }
}
