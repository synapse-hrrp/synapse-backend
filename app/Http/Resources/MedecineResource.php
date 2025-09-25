<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MedecineResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'patient_id'  => $this->patient_id,
            'visite_id'   => $this->visite_id,
            // 'service_id' => $this->service_id, // décommente si tu as ajouté la colonne
            'soignant_id' => $this->soignant_id,

            // Données médicales
            'date_acte'        => optional($this->date_acte)->toIso8601String(),
            'motif'            => $this->motif,
            'diagnostic'       => $this->diagnostic,
            'examen_clinique'  => $this->examen_clinique,
            'traitements'      => $this->traitements,
            'observation'      => $this->observation,
            'tension_arterielle'    => $this->tension_arterielle,
            'temperature'           => $this->temperature,
            'frequence_cardiaque'   => $this->frequence_cardiaque,
            'frequence_respiratoire'=> $this->frequence_respiratoire,
            'statut'           => $this->statut,

            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'deleted_at' => optional($this->deleted_at)->toIso8601String(),

            // Relations
            'patient' => $this->whenLoaded('patient', function () {
                return [
                    'id'             => $this->patient->id,
                    'nom'            => $this->patient->nom ?? null,
                    'prenom'         => $this->patient->prenom ?? null,
                    'numero_dossier' => $this->patient->numero_dossier ?? null,
                ];
            }),

            'visite' => $this->whenLoaded('visite', function () {
                return [
                    'id'            => $this->visite->id,
                    'service_id'    => $this->visite->service_id,
                    'medecin_id'    => $this->visite->medecin_id, // = soignant_id
                    'heure_arrivee' => optional($this->visite->heure_arrivee)->toIso8601String(),
                    'statut'        => $this->visite->statut,
                ];
            }),

            // ⚠️ soignant = Personnel (pas User)
            'soignant' => $this->whenLoaded('soignant', function () {
                return [
                    'id'         => $this->soignant->id,
                    'first_name' => $this->soignant->first_name,
                    'last_name'  => $this->soignant->last_name,
                    'full_name'  => $this->soignant->full_name, // via $appends dans Personnel
                    'job_title'  => $this->soignant->job_title,
                    'service_id' => $this->soignant->service_id,
                    // si tu veux aussi exposer l’email lié à l’utilisateur :
                    // 'user' => $this->soignant->relationLoaded('user') ? [
                    //     'id'    => $this->soignant->user->id,
                    //     'email' => $this->soignant->user->email,
                    // ] : null,
                ];
            }),
        ];
    }
}
