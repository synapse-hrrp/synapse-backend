<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AruResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                       => $this->id,
            'patient_id'               => $this->patient_id,
            'visite_id'                => $this->visite_id,
            'soignant_id'              => $this->soignant_id,

            'date_acte'                => $this->date_acte,
            'motif'                    => $this->motif,
            'triage_niveau'            => $this->triage_niveau,
            'tension_arterielle'       => $this->tension_arterielle,
            'temperature'              => $this->temperature,
            'frequence_cardiaque'      => $this->frequence_cardiaque,
            'frequence_respiratoire'   => $this->frequence_respiratoire,
            'saturation'               => $this->saturation,
            'douleur_echelle'          => $this->douleur_echelle,
            'glasgow'                  => $this->glasgow,
            'examens_complementaires'  => $this->examens_complementaires,
            'traitements'              => $this->traitements,
            'observation'              => $this->observation,
            'statut'                   => $this->statut,

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
