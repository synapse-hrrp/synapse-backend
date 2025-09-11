<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                 => $this->id,
            'patient_id'         => $this->patient_id,
            'visite_id'          => $this->visite_id,
            'soignant_id'        => $this->soignant_id,
            'medecin_id'         => $this->medecin_id,

            'date_acte'          => optional($this->date_acte)->toJSON(),

            'categorie'          => $this->categorie,
            'type_consultation'  => $this->type_consultation,

            'motif'              => $this->motif,
            'examen_clinique'    => $this->examen_clinique,
            'diagnostic'         => $this->diagnostic,
            'prescriptions'      => $this->prescriptions,
            'orientation_service'=> $this->orientation_service,

            'donnees_specifiques'=> $this->donnees_specifiques,
            'statut'             => $this->statut,

            'patient'  => new PatientResource($this->whenLoaded('patient')),
            'visite'   => new VisiteResource($this->whenLoaded('visite')),
            'soignant' => $this->whenLoaded('soignant', fn() => [
                'id'    => $this->soignant->id,
                'name'  => $this->soignant->name,
                'email' => $this->soignant->email,
            ]),
            'medecin' => $this->whenLoaded('medecin', fn() => [ // 👈
                'id' => $this->medecin->id,
                'name' => $this->medecin->name,
                'email' => $this->medecin->email,
            ]),

            'created_at' => optional($this->created_at)->toJSON(),
            'updated_at' => optional($this->updated_at)->toJSON(),
            'deleted_at' => optional($this->deleted_at)->toJSON(),
        ];
    }
}
