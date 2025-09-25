<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SanitaireResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'               => $this->id,
            'patient_id'       => $this->patient_id,
            'visite_id'        => $this->visite_id,
            'soignant_id'      => $this->soignant_id,

            'date_acte'        => optional($this->date_acte)->toISOString(),
            'date_debut'       => optional($this->date_debut)->toISOString(),
            'date_fin'         => optional($this->date_fin)->toISOString(),

            'type_action'      => $this->type_action,
            'zone'             => $this->zone,
            'sous_zone'        => $this->sous_zone,
            'niveau_risque'    => $this->niveau_risque,

            'produits_utilises'=> $this->produits_utilises,
            'equipe'           => $this->equipe, // cast array côté modèle
            'duree_minutes'    => $this->duree_minutes,
            'cout'             => is_null($this->cout) ? null : (float)$this->cout,

            'observation'      => $this->observation,
            'statut'           => $this->statut,

            // Relations minimales
            'patient'  => $this->whenLoaded('patient'),
            'visite'   => $this->whenLoaded('visite'),
            'soignant' => $this->whenLoaded('soignant', function () {
                return [
                    'id'    => $this->soignant->id,
                    'name'  => $this->soignant->name ?? null,
                    'email' => $this->soignant->email ?? null,
                ];
            }),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
            'deleted_at' => optional($this->deleted_at)->toISOString(),
        ];
    }
}
