<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Facture, Reglement};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{DB, Cache};

class ReglementController extends Controller
{
    // POST /factures/{facture}/reglements
    public function store(Request $request, Facture $facture): JsonResponse
    {
        $validated = $request->validate([
            'montant'   => ['required','numeric','min:0.01'],
            'mode'      => ['required','string','max:20'], // CASH|MOMO|CARTE...
            'reference' => ['nullable','string','max:100'],
        ]);

        // Idempotency (facultatif mais recommandé)
        $key = $request->header('Idempotency-Key');
        if ($key && Cache::has("reglement:$key")) {
            return response()->json(Cache::get("reglement:$key"), 200);
        }

        $payload = DB::transaction(function () use ($facture, $validated) {
            /** @var Reglement $reglement */
            $reglement = $facture->reglements()->create([
                'montant'   => $validated['montant'],
                'mode'      => $validated['mode'],
                'reference' => $validated['reference'] ?? null,
                'devise'    => $facture->devise,
            ]);

            $facture->recalc(); // met à jour total, dû, statut et éventuellement visite PAYEE

            return [
                'data' => [
                    'reglement' => [
                        'id'         => $reglement->id,
                        'montant'    => (string) $reglement->montant,
                        'mode'       => $reglement->mode,
                        'reference'  => $reglement->reference,
                        'created_at' => $reglement->created_at->toIso8601String(),
                    ],
                    'facture' => [
                        'id'            => $facture->id,
                        'numero'        => $facture->numero,
                        'statut'        => $facture->statut,
                        'montant_total' => (string) $facture->montant_total,
                        'montant_du'    => (string) $facture->montant_du,
                    ],
                    'visite' => [
                        'id'     => optional($facture->visite)->id,
                        'statut' => optional($facture->visite)->statut,
                    ]
                ]
            ];
        });

        if ($key) Cache::put("reglement:$key", $payload, now()->addHours(12));

        return response()->json($payload, 201);
    }
}
