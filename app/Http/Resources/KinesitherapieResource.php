<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KinesitherapieResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                 => $this->id,
            'patient_id'         => $this->patient_id,
            'visite_id'          => $this->visite_id,
            'soignant_id'        => $this->soignant_id,

            // Dates en ISO (string) si présentes
            'date_acte'          => optional($this->date_acte)->toISOString(),

            // Champs texte
            'motif'              => $this->motif,
            'diagnostic'         => $this->diagnostic,
            'evaluation'         => $this->evaluation,
            'objectifs'          => $this->objectifs,
            'techniques'         => $this->techniques,
            'zone_traitee'       => $this->zone_traitee,

            // Numériques castés proprement
            'intensite_douleur'  => is_null($this->intensite_douleur) ? null : (int) $this->intensite_douleur,
            'echelle_borg'       => is_null($this->echelle_borg) ? null : (int) $this->echelle_borg,
            'nombre_seances'     => is_null($this->nombre_seances) ? null : (int) $this->nombre_seances,
            'duree_minutes'      => is_null($this->duree_minutes) ? null : (int) $this->duree_minutes,

            'resultats'          => $this->resultats,
            'conseils'           => $this->conseils,
            'statut'             => $this->statut,

            // Relations minimales
            'patient'  => $this->whenLoaded('patient', function () {
                return [
                    'id'             => $this->patient->id,
                    'nom'            => $this->patient->nom ?? null,
                    'prenom'         => $this->patient->prenom ?? null,
                    'numero_dossier' => $this->patient->numero_dossier ?? null,
                ];
            }),

            'visite'   => $this->whenLoaded('visite', function () {
                return [
                    'id'           => $this->visite->id,
                    'service_id'   => $this->visite->service_id,
                    'medecin_id'   => $this->visite->medecin_id,
                    'heure_arrivee'=> optional($this->visite->heure_arrivee)->toISOString(),
                    'statut'       => $this->visite->statut,
                ];
            }),

            // Soignant = Personnel (full_name depuis l’accessor du modèle Personnel)
            'soignant' => $this->whenLoaded('soignant', function () {
                return [
                    'id'        => $this->soignant->id,
                    'full_name' => $this->soignant->full_name ?? trim(
                        ($this->soignant->first_name ?? '').' '.($this->soignant->last_name ?? '')
                    ),
                    'job_title' => $this->soignant->job_title ?? null,
                    // Si tu charges la relation user() dans le contrôleur, on expose aussi le compte
                    'user'      => isset($this->soignant->user)
                        ? [
                            'id'    => $this->soignant->user->id,
                            'name'  => $this->soignant->user->name ?? null,
                            'email' => $this->soignant->user->email ?? null,
                          ]
                        : null,
                ];
            }),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
            'deleted_at' => optional($this->deleted_at)->toISOString(),
        ];
    }
}
