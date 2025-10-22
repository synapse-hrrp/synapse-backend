<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Facture, Reglement};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{DB, Cache};
use Illuminate\Validation\ValidationException;

class ReglementController extends Controller
{
    // POST /api/v1/factures/{facture}/reglements
    public function store(Request $request, Facture $facture): JsonResponse
    {
        $validated = $request->validate([
            'montant'   => ['required','numeric','min:0.01'],
            'mode'      => ['required','string','max:20'], // CASH|MOMO|CARTE...
            'reference' => ['nullable','string','max:100'],
        ]);

        // Idempotency (facultatif)
        $key = $request->header('Idempotency-Key');
        if ($key && Cache::has("reglement:$key")) {
            return response()->json(Cache::get("reglement:$key"), 200);
        }

        $payload = DB::transaction(function () use ($facture, $validated) {

            // Recalcule avant (au cas où) et vérifie le reste dû
            $facture->recalc();
            $reste = (float) $facture->montant_du;

            if ($reste <= 0) {
                throw ValidationException::withMessages([
                    'montant' => 'Cette facture est déjà soldée.',
                ]);
            }

            if ((float)$validated['montant'] > $reste) {
                throw ValidationException::withMessages([
                    'montant' => 'Le montant dépasse le reste à payer ('.$reste.').',
                ]);
            }

            /** @var Reglement $reglement */
            $reglement = $facture->reglements()->create([
                'montant'   => $validated['montant'],
                'mode'      => $validated['mode'],
                'reference' => $validated['reference'] ?? null,
                'devise'    => $facture->devise,
            ]);

            // Recalcule après création
            $facture->recalc();
            $facture->loadMissing('reglements');

            // Valeurs pour la réponse
            $total  = (float) $facture->montant_total;
            $reste  = (float) $facture->montant_du;
            // ton accessor renvoie une string => on caste en float
            $paye   = (float) $facture->montant_paye;

            $response = [
                'facture' => [
                    'id'            => $facture->id,
                    'numero'        => $facture->numero,
                    'statut'        => $facture->statut,        // IMPAYEE | PARTIELLE | PAYEE | ANNULEE
                    'devise'        => $facture->devise,
                    // 🧾 ce que tu veux voir clairement :
                    'total'         => $total,                   // prix total
                    'paye'          => $paye,                    // total déjà payé (cumul règlements)
                    'reste'         => $reste,                   // ce qui reste à payer
                ],
                'reglement' => [
                    'id'         => $reglement->id,
                    'montant'    => (float) $reglement->montant, // ce que tu viens de payer
                    'mode'       => $reglement->mode,
                    'reference'  => $reglement->reference,
                    'devise'     => $reglement->devise,
                    'created_at' => $reglement->created_at->toIso8601String(),
                ],
            ];

            return ['data' => $response];
        });

        if ($key) Cache::put("reglement:$key", $payload, now()->addHours(12));

        return response()->json($payload, 201);
    }
}
