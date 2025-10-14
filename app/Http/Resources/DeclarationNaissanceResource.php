<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeclarationNaissanceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'patient_id'            => $this->patient_id,
            'mere_id'               => $this->mere_id,
            'pere_id'               => $this->pere_id,
            'service_slug'          => $this->service_slug,
            'accouchement_id'       => $this->accouchement_id,

            'created_via'           => $this->created_via,
            'created_by_user_id'    => $this->created_by_user_id,

            'date_heure_naissance'  => optional($this->date_heure_naissance)->toISOString(),
            'lieu_naissance'        => $this->lieu_naissance,
            'sexe'                  => $this->sexe,
            'poids_kg'              => $this->poids_kg,
            'taille_cm'             => $this->taille_cm,
            'apgar_1'               => $this->apgar_1,
            'apgar_5'               => $this->apgar_5,

            'numero_acte'           => $this->numero_acte,
            'officier_etat_civil'   => $this->officier_etat_civil,
            'documents'             => $this->documents_json,

            'statut'                => $this->statut,
            'date_transmission'     => optional($this->date_transmission)->toISOString(),

            // relations light
            'patient' => $this->whenLoaded('patient', fn() => [
                'id' => $this->patient->id,
                'nom' => $this->patient->nom ?? null,
                'prenom' => $this->patient->prenom ?? null,
            ]),
            'mere' => $this->whenLoaded('mere', fn() => [
                'id' => $this->mere->id,
                'nom' => $this->mere->nom ?? null,
                'prenom' => $this->mere->prenom ?? null,
            ]),
            'pere' => $this->whenLoaded('pere', fn() => [
                'id' => $this->pere->id,
                'nom' => $this->pere->nom ?? null,
                'prenom' => $this->pere->prenom ?? null,
            ]),
            'service' => $this->whenLoaded('service', fn() => [
                'slug' => $this->service->slug,
                'nom'  => $this->service->nom ?? null,
            ]),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
            'deleted_at' => optional($this->deleted_at)->toISOString(),
        ];
    }
}
