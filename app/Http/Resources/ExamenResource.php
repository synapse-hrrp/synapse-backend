<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExamenResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Ids / ancrages
            'id'             => $this->id,
            'patient_id'     => $this->patient_id,
            'service_slug'   => $this->service_slug,

            // Traçabilité d’origine
            'created_via'        => $this->created_via,       // 'labo' | 'service'
            'created_by_user_id' => $this->created_by_user_id,
            'type_origine'       => $this->type_origine,      // 'interne' | 'externe'

            // Tarification (exposition côté API)
            'tarif_code'     => $this->code_examen,           // alias pratique
            'code_examen'    => $this->code_examen,           // compat
            'nom_examen'     => $this->nom_examen,
            'prix'           => $this->prix,
            'devise'         => $this->devise,
            // 'tarif_id'     => $this->tarif_id ?? null,     // dé-commente si tu ajoutes la colonne

            // Médical
            'prelevement'          => $this->prelevement,
            'statut'               => $this->statut,          // en_attente | en_cours | termine | valide
            'valeur_resultat'      => $this->valeur_resultat,
            'unite'                => $this->unite,
            'intervalle_reference' => $this->intervalle_reference,
            'resultat_json'        => $this->resultat_json,

            // Demande/validation
            'demande_par'     => $this->demande_par,
            'date_demande'    => optional($this->date_demande)->toISOString(),
            'valide_par'      => $this->valide_par,
            'date_validation' => optional($this->date_validation)->toISOString(),

            // Facturation (id direct)
            'facture_id'      => $this->facture_id,

            // Métadonnées temporelles
            'created_at'      => optional($this->created_at)->toISOString(),
            'updated_at'      => optional($this->updated_at)->toISOString(),
            'deleted_at'      => optional($this->deleted_at)->toISOString(),

            // Relations légères
            'service' => $this->whenLoaded('service', fn () => [
                'slug' => $this->service->slug,
                'name' => $this->service->name ?? null,
            ]),

            'demandeur' => $this->whenLoaded('demandeur', fn () => [
                'id'        => $this->demandeur->id,
                'full_name' => $this->demandeur->full_name ?? null,
                'job_title' => $this->demandeur->job_title ?? null,
            ]),

            'validateur' => $this->whenLoaded('validateur', fn () => [
                'id'        => $this->validateur->id,
                'full_name' => $this->validateur->full_name ?? null,
                'job_title' => $this->validateur->job_title ?? null,
            ]),

            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                // ajoute d’autres champs patient si dispo
            ]),

            // (Optionnel) facture compacte si tu fais ->load('facture.lignes')
            'facture' => $this->whenLoaded('facture', fn () => [
                'id'            => $this->facture->id,
                'numero'        => $this->facture->numero ?? null,
                'statut'        => $this->facture->statut ?? null,
                'montant_total' => $this->facture->montant_total ?? null,
                'montant_du'    => $this->facture->montant_du ?? null,
                'devise'        => $this->facture->devise ?? null,
                'created_at'    => optional($this->facture->created_at)->toISOString(),
                'lignes'        => $this->when($this->facture->relationLoaded('lignes'), function () {
                    return $this->facture->lignes->map(fn ($l) => [
                        'id'            => $l->id,
                        'designation'   => $l->designation,
                        'quantite'      => $l->quantite,
                        'prix_unitaire' => $l->prix_unitaire,
                        'montant'       => $l->montant,
                        'tarif_id'      => $l->tarif_id,
                    ]);
                }),
            ]),
        ];
    }
}
