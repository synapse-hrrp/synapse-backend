<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EchographieResource extends JsonResource
{
    /**
     * @property \App\Models\Echographie $resource
     */
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'patient_id'         => $this->patient_id,
            'service_slug'       => $this->service_slug,
            'type_origine'       => $this->type_origine,
            'prescripteur_externe' => $this->prescripteur_externe,
            'reference_demande'  => $this->reference_demande,

            'created_via'        => $this->created_via,
            'created_by_user_id' => $this->created_by_user_id,

            'code_echo'          => $this->code_echo,
            'nom_echo'           => $this->nom_echo,
            'indication'         => $this->indication,
            'statut'             => $this->statut,
            'compte_rendu'       => $this->compte_rendu,
            'conclusion'         => $this->conclusion,
            'mesures'            => $this->mesures_json,
            'images'             => $this->images_json,

            'prix'               => $this->prix,
            'devise'             => $this->devise,
            'facture_id'         => $this->facture_id,

            'demande_par'        => $this->demande_par,
            'date_demande'       => optional($this->date_demande)->toISOString(),
            'realise_par'        => $this->realise_par,
            'date_realisation'   => optional($this->date_realisation)->toISOString(),
            'valide_par'         => $this->valide_par,
            'date_validation'    => optional($this->date_validation)->toISOString(),

            // relations minimalistes (évite N+1, charge-les au contrôleur via ->with())
            'patient'  => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'nom' => $this->patient->nom ?? null,
                'prenom' => $this->patient->prenom ?? null,
                'sexe' => $this->patient->sexe ?? null,
            ]),
            'service'  => $this->whenLoaded('service', fn () => [
                'slug' => $this->service->slug,
                'nom'  => $this->service->nom ?? null,
            ]),
            'demandeur' => $this->whenLoaded('demandeur', fn () => [
                'id' => $this->demandeur->id,
                'nom' => $this->demandeur->nom ?? null,
            ]),
            'operateur' => $this->whenLoaded('operateur', fn () => [
                'id' => $this->operateur->id,
                'nom' => $this->operateur->nom ?? null,
            ]),
            'validateur' => $this->whenLoaded('validateur', fn () => [
                'id' => $this->validateur->id,
                'nom' => $this->validateur->nom ?? null,
            ]),

            // méta utiles
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
            'deleted_at' => optional($this->deleted_at)->toISOString(),
        ];
    }
}
