<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GestionMaladeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'    => $this->id,

            'patient_id'  => $this->patient_id,
            'visite_id'   => $this->visite_id,
            'soignant_id' => $this->soignant_id,

            'date_acte'   => $this->date_acte,
            'type_action' => $this->type_action,

            'service_source'      => $this->service_source,
            'service_destination' => $this->service_destination,
            'pavillon'            => $this->pavillon,
            'chambre'             => $this->chambre,
            'lit'                 => $this->lit,

            'date_entree'           => $this->date_entree,
            'date_sortie_prevue'    => $this->date_sortie_prevue,
            'date_sortie_effective' => $this->date_sortie_effective,

            'motif'           => $this->motif,
            'diagnostic'      => $this->diagnostic,
            'examen_clinique' => $this->examen_clinique,
            'traitements'     => $this->traitements,
            'observation'     => $this->observation,

            'statut' => $this->statut,

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
