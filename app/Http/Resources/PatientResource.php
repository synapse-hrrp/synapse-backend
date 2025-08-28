<?php
// app/Http/Resources/PatientResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'numero_dossier' => $this->numero_dossier,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'sexe' => $this->sexe,
            'date_naissance' => $this->date_naissance?->toDateString(),
            'lieu_naissance' => $this->lieu_naissance,
            'age' => $this->age, // calculé
            'age_reporte' => $this->age_reporte,

            'nationalite' => $this->nationalite,
            'profession' => $this->profession,
            'adresse' => $this->adresse,
            'quartier' => $this->quartier,
            'telephone' => $this->telephone,
            'statut_matrimonial' => $this->statut_matrimonial,

            'proche_nom' => $this->proche_nom,
            'proche_tel' => $this->proche_tel,

            'groupe_sanguin' => $this->groupe_sanguin,
            'allergies' => $this->allergies,

            'assurance_id' => $this->assurance_id,
            'numero_assure' => $this->numero_assure,

            'is_active' => (bool)$this->is_active,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
