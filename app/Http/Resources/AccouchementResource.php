<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AccouchementResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                      => $this->id,
            'mere_id'                 => $this->mere_id,
            'service_slug'            => $this->service_slug,
            'created_via'             => $this->created_via,
            'created_by_user_id'      => $this->created_by_user_id,

            'date_heure_accouchement' => optional($this->date_heure_accouchement)?->toJSON(),
            'terme_gestationnel_sa'   => $this->terme_gestationnel_sa,
            'voie'                    => $this->voie,
            'presentation'            => $this->presentation,
            'type_cesarienne'         => $this->type_cesarienne,
            'score_apgar_1_5'         => $this->score_apgar_1_5,
            'poids_kg'                => $this->when(!is_null($this->poids_kg), (string)$this->poids_kg),
            'taille_cm'               => $this->when(!is_null($this->taille_cm), (string)$this->taille_cm),
            'sexe'                    => $this->sexe,
            'complications_json'      => $this->complications_json,
            'notes'                   => $this->notes,

            'statut'                  => $this->statut,

            'prix'                    => $this->when(!is_null($this->prix), (string)$this->prix),
            'devise'                  => $this->devise,
            'facture_id'              => $this->facture_id,

            'sage_femme_id'           => $this->sage_femme_id,
            'obstetricien_id'         => $this->obstetricien_id,

            'created_at'              => optional($this->created_at)?->toJSON(),
            'updated_at'              => optional($this->updated_at)?->toJSON(),
            'deleted_at'              => optional($this->deleted_at)?->toJSON(),
        ];
    }
}
