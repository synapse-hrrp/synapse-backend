<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VisiteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,

            'patient' => [
                'id'             => $this->patient_id,
                'numero_dossier' => $this->patient->numero_dossier ?? null,
                'nom'            => $this->patient->nom ?? null,
                'prenom'         => $this->patient->prenom ?? null,
                'age'            => $this->patient->age ?? null,
                'sexe'           => $this->patient->sexe ?? null,
            ],

            // ⬇️ Basé sur service_id + relation service (code/nom en lecture)
            'service' => [
                'id'   => $this->service_id,
                'code' => $this->service?->code, // null-safe
                'nom'  => $this->service?->nom,
                // (option) si tu as conservé un snapshot sans FK :
                // 'code_snapshot' => $this->service_code ?? null,
            ],

            'medecin' => [
                'id'  => $this->medecin_id,
                'nom' => $this->medecin_nom ?? ($this->medecin->name ?? null),
            ],

            'agent' => [
                'id'  => $this->agent_id,
                'nom' => $this->agent_nom,
            ],

            'heure_arrivee'         => $this->heure_arrivee?->toISOString(),
            'plaintes_motif'        => $this->plaintes_motif,
            'hypothese_diagnostic'  => $this->hypothese_diagnostic,
            'affectation_id'        => $this->affectation_id,
            'statut'                => $this->statut,
            'clos_at'               => $this->clos_at?->toISOString(),

            // Bloc prix (rendu uniquement si colonnes présentes)
            'prix' => [
                'tarif' => $this->whenLoaded('tarif', fn () => [
                    'code'    => $this->tarif->code,
                    'libelle' => $this->tarif->libelle,
                    'montant' => (float) $this->tarif->montant,
                    'devise'  => $this->tarif->devise,
                ]),
                'montant_prevu'   => $this->when(isset($this->montant_prevu), (float) ($this->montant_prevu ?? 0)),
                'remise_pct'      => $this->when(isset($this->remise_pct), (float) ($this->remise_pct ?? 0)),
                'montant_du'      => $this->when(isset($this->montant_du), (float) ($this->montant_du ?? 0)),
                'devise'          => $this->when(isset($this->devise), $this->devise),
                'statut_paiement' => $this->when(isset($this->statut_paiement), $this->statut_paiement),
                'motif_gratuite'  => $this->when(isset($this->motif_gratuite), $this->motif_gratuite),
            ],

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
