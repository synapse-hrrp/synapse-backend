<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class HospitalisationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                 => $this->id,
            'patient_id'         => $this->patient_id,
            'service_slug'       => $this->service_slug,
            'admission_no'       => $this->admission_no,
            'created_via'        => $this->created_via,
            'created_by_user_id' => $this->created_by_user_id,

            'unite'              => $this->unite,
            'chambre'            => $this->chambre,
            'lit'                => $this->lit,
            'lit_id'             => $this->lit_id,
            'medecin_traitant_id'=> $this->medecin_traitant_id,

            'motif_admission'    => $this->motif_admission,
            'diagnostic_entree'  => $this->diagnostic_entree,
            'diagnostic_sortie'  => $this->diagnostic_sortie,
            'notes'              => $this->notes,
            'prise_en_charge_json' => $this->prise_en_charge_json,

            'statut'             => $this->statut,
            'date_admission'     => optional($this->date_admission)->toISOString(),
            'date_sortie_prevue' => optional($this->date_sortie_prevue)->toISOString(),
            'date_sortie_reelle' => optional($this->date_sortie_reelle)->toISOString(),

            'facture_id'         => $this->facture_id,


            'created_at'         => optional($this->created_at)->toISOString(),
            'updated_at'         => optional($this->updated_at)->toISOString(),
            'deleted_at'         => optional($this->deleted_at)->toISOString(),
        ];
    }
}
