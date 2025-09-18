<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Facture, FactureLigne};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FactureLigneController extends Controller
{
    /**
     * POST /factures/{facture}/lignes
     * Body: { designation, quantite, prix_unitaire, tarif_id? }
     */
    public function store(Request $r, Facture $facture): JsonResponse
    {
        $data = $r->validate([
            'designation'   => ['required','string','max:255'],
            'quantite'      => ['required','numeric','min:0.01'],
            'prix_unitaire' => ['required','numeric','min:0'],
            'tarif_id'      => ['nullable','uuid','exists:tarifs,id'],
        ]);

        $ligne = DB::transaction(function () use ($facture, $data) {
            $l = $facture->lignes()->create([
                'designation'   => $data['designation'],
                'quantite'      => $data['quantite'],
                'prix_unitaire' => $data['prix_unitaire'],
                'tarif_id'      => $data['tarif_id'] ?? null,
                'montant'       => (float)$data['quantite'] * (float)$data['prix_unitaire'],
            ]);
            $facture->recalc();
            return $l;
        });

        return response()->json(['data' => $ligne->fresh()], 201);
    }

    /**
     * PUT/PATCH /lignes/{ligne}
     * Body (partiel): { designation?, quantite?, prix_unitaire?, tarif_id? }
     */
    public function update(Request $r, FactureLigne $ligne): JsonResponse
    {
        $data = $r->validate([
            'designation'   => ['sometimes','string','max:255'],
            'quantite'      => ['sometimes','numeric','min:0.01'],
            'prix_unitaire' => ['sometimes','numeric','min:0'],
            'tarif_id'      => ['nullable','uuid','exists:tarifs,id'],
        ]);

        DB::transaction(function () use ($ligne, $data) {
            $ligne->fill($data);

            // Recalcul du montant si quantite/prix changent
            if (array_key_exists('quantite', $data) || array_key_exists('prix_unitaire', $data)) {
                $q  = (float)($data['quantite'] ?? $ligne->quantite);
                $pu = (float)($data['prix_unitaire'] ?? $ligne->prix_unitaire);
                $ligne->montant = $q * $pu;
            }

            $ligne->save();
            $ligne->facture?->recalc();
        });

        return response()->json(['data' => $ligne->fresh()]);
    }

    /**
     * DELETE /lignes/{ligne}
     */
    public function destroy(FactureLigne $ligne): JsonResponse
    {
        DB::transaction(function () use ($ligne) {
            $facture = $ligne->facture;
            $ligne->delete();
            $facture?->recalc();
        });

        return response()->json([], 204);
    }
}
