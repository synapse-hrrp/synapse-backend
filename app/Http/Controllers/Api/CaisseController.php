<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Visite, Facture, FactureLigne};
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CaisseController extends Controller
{
    // POST /visites/{visite}/envoyer-caisse
    public function store(Visite $visite): JsonResponse
    {
        // Charger ce qui manque
        $visite->loadMissing(['service','tarif','patient','facture']);

        // Idempotence simple : si facture existe déjà pour la visite -> 409
        if ($visite->facture) {
            return response()->json([
                'message' => 'Cette visite a déjà une facture.',
                'data' => [
                    'facture' => $visite->facture->load('lignes'),
                    'visite'  => ['id' => $visite->id, 'statut' => $visite->statut],
                ],
            ], 409);
        }

        $facture = DB::transaction(function () use ($visite) {
            // 1) Créer la facture
            $facture = Facture::create([
                'visite_id'     => $visite->id,
                'patient_id'    => $visite->patient_id,
                'montant_total' => $visite->montant_prevu ?? 0,
                'montant_du'    => $visite->montant_du ?? ($visite->montant_prevu ?? 0),
                'devise'        => $visite->devise ?? 'CDF',
                'statut'        => 'IMPAYEE',
            ]);

            // 2) Ligne par défaut (acte principal)
            FactureLigne::create([
                'facture_id'    => $facture->id,
                'tarif_id'      => $visite->tarif_id,
                'designation'   => $visite->service->nom ?? 'Consultation',
                'quantite'      => 1,
                'prix_unitaire' => $visite->montant_prevu ?? 0,
                'montant'       => $visite->montant_prevu ?? 0,
            ]);

            // 3) Mettre la visite "à encaisser"
            $visite->update(['statut' => 'A_ENCAISSER']);

            // 4) Recalcule sécurité
            $facture->recalc();

            return $facture->fresh(['lignes','visite']);
        });

        return response()->json([
            'data' => [
                'facture' => $facture,
                'visite'  => ['id' => $visite->id, 'statut' => $visite->fresh()->statut],
            ],
            'links' => [
                'self' => route('factures.show', $facture),
                'pdf'  => route('factures.pdf',  $facture),
            ]
        ], 201);
    }
}
