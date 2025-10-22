<?php

namespace App\Services\Pharmacie;

use App\Models\Facture;
use App\Models\FactureLigne;
use App\Models\Pharmacie\PharmaCart;
use Illuminate\Support\Facades\DB;

class PharmacyFactureService
{
    public function createFromCart(PharmaCart $cart): Facture
    {
        return DB::transaction(function () use ($cart) {
            // 1) En-tête : on propage visite_id & patient_id du panier
            $facture = Facture::create([
                'visite_id'     => $cart->visite_id,               // ✅ lié si interne, null si comptoir
                'patient_id'    => $cart->patient_id,              // ✅
                'montant_total' => 0,                              // recalculé ensuite
                'montant_du'    => 0,                              // recalculé ensuite
                'devise'        => $cart->currency ?? 'CDF',
                'statut'        => 'IMPAYEE',
            ]);

            // 2) Lignes : une par article du panier
            $cart->loadMissing('lines.article:id,name');
            foreach ($cart->lines as $l) {
                FactureLigne::create([
                    'facture_id'    => $facture->id,
                    'tarif_id'      => null,
                    'designation'   => $l->article->name,
                    'quantite'      => $l->quantity,
                    'prix_unitaire' => $l->unit_price,
                    // 'montant' null => auto = quantite * prix_unitaire (boot du modèle)
                ]);
            }

            // 3) Recalcul (total / dû / statut)
            $facture->load('lignes','reglements');
            $facture->recalc();

            return $facture->fresh('lignes');
        });
    }
}
