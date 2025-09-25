<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MaterniteResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'           => $this->id,

            'patient_id'   => $this->patient_id,
            'visite_id'    => $this->visite_id,
            'soignant_id'  => $this->soignant_id,
            // exposer service_id si tu as ajouté la colonne (sinon restera null)
            'service_id'   => $this->when(\Illuminate\Support\Facades\Schema::hasColumn('maternites','service_id'), $this->service_id),

            // Dates (ISO)
            'date_acte'    => optional($this->date_acte)->toISOString(),

            // Données médicales
            'motif'        => $this->motif,
            'diagnostic'   => $this->diagnostic,

            'terme_grossesse'        => $this->terme_grossesse,
            'age_gestationnel'       => $this->age_gestationnel,
            'mouvements_foetaux'     => (bool) $this->mouvements_foetaux,

            'tension_arterielle'     => $this->tension_arterielle,
            'temperature'            => $this->temperature !== null ? (float) $this->temperature : null,
            'frequence_cardiaque'    => $this->frequence_cardiaque,
            'frequence_respiratoire' => $this->frequence_respiratoire,

            'hauteur_uterine'               => $this->hauteur_uterine,
            'presentation'                  => $this->presentation,
            'battements_cardiaques_foetaux' => $this->battements_cardiaques_foetaux,
            'col_uterin'                    => $this->col_uterin,
            'pertes'                        => $this->pertes,

            'examen_clinique' => $this->examen_clinique,
            'traitements'     => $this->traitements,
            'observation'     => $this->observation,

            'statut'          => $this->statut,

            // Relations
            // Si tu utilises déjà PatientResource / VisiteResource, garde ces lignes.
            'patient'  => $this->whenLoaded('patient', fn () => new PatientResource($this->patient)),
            'visite'   => $this->whenLoaded('visite', fn () => new VisiteResource($this->visite)),

            // soignant = Personnel (pas User)
            'soignant' => $this->whenLoaded('soignant', function () {
                // Personnel a full_name ; l'email peut venir de $this->soignant->user?->email
                return [
                    'id'        => $this->soignant->id,
                    'full_name' => $this->soignant->full_name,
                    'job_title' => $this->soignant->job_title,
                    'email'     => optional($this->soignant->user)->email, // peut être null
                ];
            }),

            // Timestamps en ISO
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
            'deleted_at' => optional($this->deleted_at)->toISOString(),
        ];
    }
}
