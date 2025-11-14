<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => (string) $this->id,
            'numero_dossier'   => $this->numero_dossier,
            'nom'              => $this->nom,
            'prenom'           => $this->prenom,
            'sexe'             => $this->sexe,
            'date_naissance'   => $this->date_naissance?->toDateString(),
            'telephone'        => $this->telephone,
            'adresse'          => $this->adresse,
            'is_active'        => (bool) $this->is_active,
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
