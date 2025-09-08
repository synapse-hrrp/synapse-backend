<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'               => $this->id,
            'numero'           => $this->numero,
            'patient_id'       => $this->patient_id,
            'visite_id'        => $this->visite_id,
            'devise'           => $this->devise,
            'remise'           => (float)$this->remise,
            'montant_total'    => (float)$this->montant_total,
            'montant_paye'     => (float)$this->montant_paye,
            'statut_paiement'  => $this->statut_paiement,

            'cree_par' => $this->whenLoaded('creePar', function () {
                return [
                    'id'    => $this->creePar->id,
                    'name'  => $this->creePar->name,
                    'email' => $this->creePar->email,
                ];
            }),

            'lignes' => $this->whenLoaded('lignes', function () {
                return $this->lignes->map(fn($it) => [
                    'id'            => $it->id,
                    'service_slug'  => $it->service_slug,
                    'reference_id'  => $it->reference_id,
                    'libelle'       => $it->libelle,
                    'quantite'      => (int)$it->quantite,
                    'prix_unitaire' => (float)$it->prix_unitaire,
                    'total_ligne'   => (float)$it->total_ligne,
                ]);
            }),

            'paiements' => $this->whenLoaded('paiements', function () {
                return $this->paiements->map(fn($p) => [
                    'id'            => $p->id,
                    'montant'       => (float)$p->montant,
                    'devise'        => $p->devise,
                    'methode'       => $p->methode,
                    'date_paiement' => optional($p->date_paiement)->toISOString(),
                    'recu_par'      => $p->whenLoaded('recuPar', fn() => [
                        'id'    => $p->recuPar->id,
                        'name'  => $p->recuPar->name,
                        'email' => $p->recuPar->email,
                    ]),
                ]);
            }),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
