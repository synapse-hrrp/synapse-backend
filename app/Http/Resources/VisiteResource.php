<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VisiteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,

            // Patient (affiche les infos détaillées si la relation est chargée)
            'patient' => $this->when($this->relationLoaded('patient') || $this->patient_id, [
                'id'             => $this->patient_id,
                'numero_dossier' => $this->whenLoaded('patient', fn()=> $this->patient?->numero_dossier),
                'nom'            => $this->whenLoaded('patient', fn()=> $this->patient?->nom),
                'prenom'         => $this->whenLoaded('patient', fn()=> $this->patient?->prenom),
                'age'            => $this->whenLoaded('patient', fn()=> $this->patient?->age),
                'sexe'           => $this->whenLoaded('patient', fn()=> $this->patient?->sexe),
            ]),

            // Service (code/nom via relation si chargée)
            'service' => [
                'id'   => $this->service_id,
                'code' => $this->whenLoaded('service', fn()=> $this->service?->code),
                'name' => $this->whenLoaded('service', fn()=> $this->service?->name),
            ],

            // Médecin : snapshot prioritaire, sinon relation personnels.full_name
            'medecin' => [
                'id'  => $this->medecin_id,
                'nom' => $this->medecin_nom
                    ?? $this->whenLoaded('medecin', fn()=> $this->medecin?->full_name),
            ],

            // Agent : snapshot prioritaire, sinon relation personnels.full_name
            'agent' => [
                'id'  => $this->agent_id,
                'nom' => $this->agent_nom
                    ?? $this->whenLoaded('agent', fn()=> $this->agent?->full_name),
            ],

            'heure_arrivee'        => $this->heure_arrivee?->toISOString(),
            'plaintes_motif'       => $this->plaintes_motif,
            'hypothese_diagnostic' => $this->hypothese_diagnostic,
            'affectation_id'       => $this->affectation_id,
            'statut'               => $this->statut,
            'clos_at'              => $this->clos_at?->toISOString(),

            // Bloc prix (uniquement si colonnes/relations présentes)
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
