<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MaterniteResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'    => $this->id,

            'patient_id'  => $this->patient_id,
            'visite_id'   => $this->visite_id,
            'soignant_id' => $this->soignant_id,

            'date_acte'   => $this->date_acte,

            'motif'       => $this->motif,
            'diagnostic'  => $this->diagnostic,

            'terme_grossesse'              => $this->terme_grossesse,
            'age_gestationnel'             => $this->age_gestationnel,
            'mouvements_foetaux'           => $this->mouvements_foetaux,

            'tension_arterielle'           => $this->tension_arterielle,
            'temperature'                  => $this->temperature,
            'frequence_cardiaque'          => $this->frequence_cardiaque,
            'frequence_respiratoire'       => $this->frequence_respiratoire,

            'hauteur_uterine'              => $this->hauteur_uterine,
            'presentation'                 => $this->presentation,
            'battements_cardiaques_foetaux'=> $this->battements_cardiaques_foetaux,
            'col_uterin'                   => $this->col_uterin,
            'pertes'                       => $this->pertes,

            'examen_clinique' => $this->examen_clinique,
            'traitements'     => $this->traitements,
            'observation'     => $this->observation,

            'statut' => $this->statut,

            'patient'  => new PatientResource($this->whenLoaded('patient')),
            'visite'   => new VisiteResource($this->whenLoaded('visite')),
            'soignant' => $this->whenLoaded('soignant', function () {
                return [
                    'id'    => $this->soignant->id,
                    'name'  => $this->soignant->name,
                    'email' => $this->soignant->email,
                ];
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
