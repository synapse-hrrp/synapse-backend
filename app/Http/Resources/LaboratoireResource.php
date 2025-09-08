<?php

// app/Http/Resources/LaboratoireResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LaboratoireResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'patient_id'    => $this->patient_id,
            'visite_id'     => $this->visite_id,

            // Français
            'code_test'      => $this->test_code,
            'nom_test'       => $this->test_name,
            'prelevement'    => $this->specimen,
            'statut'         => $this->status,
            'valeur'         => $this->result_value,
            'unite'          => $this->unit,
            'intervalle_ref' => $this->ref_range,
            'resultat_json'  => $this->result_json,

            'prix'           => $this->price,
            'devise'         => $this->currency,
            'facture_id'     => $this->invoice_id,

            'demande_par'    => $this->requested_by,
            'demande_le'     => optional($this->requested_at)->toISOString(),
            'valide_par'     => $this->validated_by,
            'valide_le'      => optional($this->validated_at)->toISOString(),

            'created_at'     => optional($this->created_at)->toISOString(),
            'updated_at'     => optional($this->updated_at)->toISOString(),
            'deleted_at'     => optional($this->deleted_at)->toISOString(),
        ];
    }
}
