<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EchographieResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'patient_id'      => $this->patient_id,
            'service_slug'    => $this->service_slug,
            'type_origine'    => $this->type_origine,
            'created_via'     => $this->created_via,
            'created_by_user_id' => $this->created_by_user_id,

            'code_echo'       => $this->code_echo,
            'nom_echo'        => $this->nom_echo,
            'indication'      => $this->indication,
            'statut'          => $this->statut,
            'compte_rendu'    => $this->compte_rendu,
            'conclusion'      => $this->conclusion,
            'mesures_json'    => $this->mesures_json,
            'images_json'     => $this->images_json,

            'prix'            => $this->prix,
            'devise'          => $this->devise,
            'facture_id'      => $this->facture_id,

            'demande_par'     => $this->demande_par,
            'date_demande'    => optional($this->date_demande)->toISOString(),
            'realise_par'     => $this->realise_par,
            'date_realisation'=> optional($this->date_realisation)->toISOString(),
            'valide_par'      => $this->valide_par,
            'date_validation' => optional($this->date_validation)->toISOString(),

        

            'created_at'      => optional($this->created_at)->toISOString(),
            'updated_at'      => optional($this->updated_at)->toISOString(),
            'deleted_at'      => optional($this->deleted_at)->toISOString(),
        ];
    }
}
