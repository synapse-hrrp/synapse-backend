<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SanitaireResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'    => $this->id,

            'patient_id'  => $this->patient_id,
            'visite_id'   => $this->visite_id,
            'soignant_id' => $this->soignant_id,

            'date_acte'   => $this->date_acte,
            'date_debut'  => $this->date_debut,
            'date_fin'    => $this->date_fin,

            'type_action'   => $this->type_action,
            'zone'          => $this->zone,
            'sous_zone'     => $this->sous_zone,
            'niveau_risque' => $this->niveau_risque,

            'produits_utilises' => $this->produits_utilises,
            'equipe'            => $this->equipe,
            'duree_minutes'     => $this->duree_minutes,
            'cout'              => $this->cout,

            'observation' => $this->observation,
            'statut'      => $this->statut,

            'patient'  => $this->whenLoaded('patient', function () { return [
                'id' => $this->patient->id,
                'nom' => $this->patient->nom,
                'prenom' => $this->patient->prenom,
                'numero_dossier' => $this->patient->numero_dossier,
            ];}),
            'visite'   => $this->whenLoaded('visite'),
            'soignant' => $this->whenLoaded('soignant', function () { return [
                'id'    => $this->soignant->id,
                'name'  => $this->soignant->name,
                'email' => $this->soignant->email,
            ];}),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
