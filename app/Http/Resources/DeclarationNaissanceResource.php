<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeclarationNaissanceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                  => $this->id,
            'mere_id'             => $this->mere_id,
            'service_slug'        => $this->service_slug,
            'accouchement_id'     => $this->accouchement_id,
            'created_via'         => $this->created_via,
            'created_by_user_id'  => $this->created_by_user_id,

            'bebe_nom'            => $this->bebe_nom,
            'bebe_prenom'         => $this->bebe_prenom,
            'pere_nom'            => $this->pere_nom,
            'pere_prenom'         => $this->pere_prenom,

            'date_heure_naissance'=> optional($this->date_heure_naissance)->toISOString(),
            'lieu_naissance'      => $this->lieu_naissance,
            'sexe'                => $this->sexe,
            'poids_kg'            => $this->poids_kg,
            'taille_cm'           => $this->taille_cm,
            'apgar_1'             => $this->apgar_1,
            'apgar_5'             => $this->apgar_5,

            'numero_acte'         => $this->numero_acte,
            'officier_etat_civil' => $this->officier_etat_civil,
            'documents_json'      => $this->documents_json,

            'statut'              => $this->statut,
            'date_transmission'   => optional($this->date_transmission)->toISOString(),



            'created_at'          => optional($this->created_at)->toISOString(),
            'updated_at'          => optional($this->updated_at)->toISOString(),
            'deleted_at'          => optional($this->deleted_at)->toISOString(),
        ];
    }
}
