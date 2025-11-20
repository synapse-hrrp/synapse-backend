<?php

namespace App\Services\Pharmacie;

use App\Models\Facture;
use App\Models\FactureLigne;
use App\Models\Service;
use App\Models\Pharmacie\PharmaCart;
use Illuminate\Support\Facades\DB;

class PharmacyFactureService
{
    /**
     * Crée une facture à partir d’un panier pharmacie.
     * - patient_id & visite_id peuvent être null (vente comptoir)
     * - on remplit service_id :
     *      - si visite liée -> visite.service_id
     *      - sinon -> service "pharmacie" (par slug ou name)
     */
    public function createFromCart(PharmaCart $cart): Facture
    {
        return DB::transaction(function () use ($cart) {
            $cart->loadMissing(['lines.article', 'visite']);

            // 1) Déterminer le service_id
            $serviceId = null;

            // a) si le panier est rattaché à une visite avec service_id
            if ($cart->visite && $cart->visite->service_id) {
                $serviceId = (int) $cart->visite->service_id;
            } else {
                // b) sinon, on tente de trouver le service PHARMACIE
                $serviceId = Service::where('slug', 'pharmacie')
                    ->orWhere('name', 'Pharmacie')
                    ->value('id');

                $serviceId = $serviceId ? (int) $serviceId : null;
            }

            // 2) En-tête facture
            $facture = Facture::create([
                'visite_id'     => $cart->visite_id,               // peut être null
                'patient_id'    => $cart->patient_id,              // peut être null
                'service_id'    => $serviceId,                     // 👈 clé directe pour filtrage caisse
                'montant_total' => 0,                              // recalculé ensuite
                'montant_du'    => 0,                              // recalculé ensuite
                'devise'        => $cart->currency ?? 'CDF',
                'statut'        => 'IMPAYEE',
            ]);

            // 3) Lignes : une par article du panier
            foreach ($cart->lines as $l) {
                FactureLigne::create([
                    'facture_id'    => $facture->id,
                    'tarif_id'      => null,
                    'designation'   => $l->article->name ?? 'Article pharmacie',
                    'quantite'      => $l->quantity,
                    'prix_unitaire' => $l->unit_price ?? 0,
                    // montant : soit line_ttc, soit quantity * unit_price
                    'montant'       => $l->line_ttc ?? ($l->quantity * ($l->unit_price ?? 0)),
                ]);
            }

            // 4) Recalcul (total / dû / statut)
            $facture->load('lignes','reglements');
            $facture->recalc();

            return $facture->fresh('lignes');
        });
    }
}
