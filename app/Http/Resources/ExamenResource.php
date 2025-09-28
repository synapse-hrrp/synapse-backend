<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExamenResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                   => $this->id,
            'patient_id'           => $this->patient_id,
            'service_slug'         => $this->service_slug,
            'type_origine'         => $this->type_origine,

            'code_examen'          => $this->code_examen,
            'nom_examen'           => $this->nom_examen,
            'prelevement'          => $this->prelevement,

            'statut'               => $this->statut,

            'valeur_resultat'      => $this->valeur_resultat,
            'unite'                => $this->unite,
            'intervalle_reference' => $this->intervalle_reference,
            'resultat_json'        => $this->resultat_json,

            'prix'                 => $this->prix,
            'devise'               => $this->devise,
            'facture_id'           => $this->facture_id,

            'demande_par'          => $this->demande_par,
            'date_demande'         => optional($this->date_demande)->toISOString(),
            'valide_par'           => $this->valide_par,
            'date_validation'      => optional($this->date_validation)->toISOString(),

            'created_at'           => optional($this->created_at)->toISOString(),
            'updated_at'           => optional($this->updated_at)->toISOString(),
            'deleted_at'           => optional($this->deleted_at)->toISOString(),

            // Extras pratiques si les relations sont chargées
            'service' => $this->whenLoaded('service', fn() => [
                'slug' => $this->service->slug,
                'name' => $this->service->name ?? null,
            ]),
            'demandeur' => $this->whenLoaded('demandeur', fn() => [
                'id' => $this->demandeur->id,
                'full_name' => $this->demandeur->full_name ?? null,
                'job_title' => $this->demandeur->job_title ?? null,
            ]),
            'validateur' => $this->whenLoaded('validateur', fn() => [
                'id' => $this->validateur->id,
                'full_name' => $this->validateur->full_name ?? null,
                'job_title' => $this->validateur->job_title ?? null,
            ]),
            'patient' => $this->whenLoaded('patient', fn() => [
                'id' => $this->patient->id,
                // ajoute d’autres champs patient si dispo
            ]),
        ];
    }
}
