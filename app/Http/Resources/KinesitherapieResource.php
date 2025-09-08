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

            'date_acte'          => $this->date_acte,
            'motif'              => $this->motif,
            'diagnostic'         => $this->diagnostic,
            'evaluation'         => $this->evaluation,
            'objectifs'          => $this->objectifs,
            'techniques'         => $this->techniques,
            'zone_traitee'       => $this->zone_traitee,
            'intensite_douleur'  => $this->intensite_douleur,
            'echelle_borg'       => $this->echelle_borg,
            'nombre_seances'     => $this->nombre_seances,
            'duree_minutes'      => $this->duree_minutes,
            'resultats'          => $this->resultats,
            'conseils'           => $this->conseils,
            'statut'             => $this->statut,

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
