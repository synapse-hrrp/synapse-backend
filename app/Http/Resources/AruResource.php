<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AruResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                      => $this->id,
            'patient_id'              => $this->patient_id,
            'visite_id'               => $this->visite_id,
            'service_id'              => $this->service_id,
            'soignant_id'             => $this->soignant_id,

            // champs médicaux
            'date_acte'               => optional($this->date_acte)->toIso8601String(),
            'motif'                   => $this->motif,
            'triage_niveau'           => $this->triage_niveau,
            'tension_arterielle'      => $this->tension_arterielle,
            'temperature'             => $this->temperature,
            'frequence_cardiaque'     => $this->frequence_cardiaque,
            'frequence_respiratoire'  => $this->frequence_respiratoire,
            'saturation'              => $this->saturation,
            'douleur_echelle'         => $this->douleur_echelle,
            'glasgow'                 => $this->glasgow,
            'examens_complementaires' => $this->examens_complementaires,
            'traitements'             => $this->traitements,
            'observation'             => $this->observation,
            'statut'                  => $this->statut,

            // relations
            'patient'  => $this->whenLoaded('patient', function () {
                return [
                    'id'              => $this->patient->id,
                    'nom'             => $this->patient->nom,
                    'prenom'          => $this->patient->prenom,
                    'numero_dossier'  => $this->patient->numero_dossier,
                ];
            }),

            'visite' => $this->whenLoaded('visite', function () {
                return [
                    'id'            => $this->visite->id,
                    'service_id'    => $this->visite->service_id,
                    'medecin_id'    => $this->visite->medecin_id, // doit correspondre à soignant_id
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
                    // si tu veux inclure l'utilisateur lié :
                    // 'user' => $this->soignant->relationLoaded('user') ? [
                    //     'id'    => $this->soignant->user->id,
                    //     'email' => $this->soignant->user->email,
                    // ] : null,
                ];
            }),

            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'deleted_at' => optional($this->deleted_at)->toIso8601String(),
        ];
    }
}
