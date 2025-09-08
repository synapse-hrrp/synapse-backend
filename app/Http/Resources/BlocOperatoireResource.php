<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BlocOperatoireResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                 => $this->id,
            'patient_id'         => $this->patient_id,
            'visite_id'          => $this->visite_id,

            'soignant_id'        => $this->soignant_id,
            'chirurgien_id'      => $this->chirurgien_id,
            'anesthesiste_id'    => $this->anesthesiste_id,
            'infirmier_bloc_id'  => $this->infirmier_bloc_id,

            'date_intervention'  => $this->date_intervention,
            'type_intervention'  => $this->type_intervention,
            'cote'               => $this->cote,
            'classification_asa' => $this->classification_asa,
            'type_anesthesie'    => $this->type_anesthesie,

            'heure_entree_bloc'  => $this->heure_entree_bloc,
            'heure_debut'        => $this->heure_debut,
            'heure_fin'          => $this->heure_fin,
            'heure_sortie_bloc'  => $this->heure_sortie_bloc,
            'duree_minutes'      => $this->duree_minutes,

            'indication'         => $this->indication,
            'gestes_realises'    => $this->gestes_realises,
            'compte_rendu'       => $this->compte_rendu,
            'incident_accident'  => $this->incident_accident,
            'pertes_sanguines'   => $this->pertes_sanguines,
            'antibioprophylaxie' => $this->antibioprophylaxie,

            'destination_postop' => $this->destination_postop,
            'consignes_postop'   => $this->consignes_postop,
            'statut'             => $this->statut,

            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'nom' => $this->patient->nom,
                'prenom' => $this->patient->prenom,
                'numero_dossier' => $this->patient->numero_dossier,
            ]),
            'visite'   => $this->whenLoaded('visite'),
            'soignant' => $this->whenLoaded('soignant', fn () => [
                'id' => $this->soignant->id,
                'name' => $this->soignant->name,
                'email' => $this->soignant->email,
            ]),
            'chirurgien' => $this->whenLoaded('chirurgien', fn () => [
                'id' => $this->chirurgien->id,
                'name' => $this->chirurgien->name,
                'email' => $this->chirurgien->email,
            ]),
            'anesthesiste' => $this->whenLoaded('anesthesiste', fn () => [
                'id' => $this->anesthesiste->id,
                'name' => $this->anesthesiste->name,
                'email' => $this->anesthesiste->email,
            ]),
            'infirmier_bloc' => $this->whenLoaded('infirmierBloc', fn () => [
                'id' => $this->infirmierBloc->id,
                'name' => $this->infirmierBloc->name,
                'email' => $this->infirmierBloc->email,
            ]),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
