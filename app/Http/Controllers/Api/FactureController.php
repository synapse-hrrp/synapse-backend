<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Facture, FactureLigne, Visite};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FactureController extends Controller
{
    // GET /factures?patient_id=&statut=&date_from=&date_to=
    public function index(Request $r): JsonResponse
    {
        $q = Facture::query()
            ->when($r->filled('patient_id'), fn($qq) => $qq->where('patient_id', $r->patient_id))
            ->when($r->filled('statut'), fn($qq) => $qq->where('statut', $r->statut))
            ->when($r->filled('date_from'), fn($qq) => $qq->whereDate('created_at', '>=', $r->date_from))
            ->when($r->filled('date_to'), fn($qq) => $qq->whereDate('created_at', '<=', $r->date_to))
            ->latest();

        return response()->json($q->paginate(20));
    }

    // GET /factures/{facture}
    public function show(Facture $facture): JsonResponse
    {
        return response()->json([
            'data' => $facture->load('lignes','reglements','visite')
        ]);
    }

    /**
     * POST /factures
     * Body: {"visite_id": "uuid-visite"}
     * Crée une facture à partir d'une visite (et met la visite en A_ENCAISSER).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'visite_id' => ['required','uuid','exists:visites,id'],
        ]);

        $facture = DB::transaction(function () use ($data) {
            /** @var Visite $visite */
            $visite = Visite::with(['service','tarif'])->findOrFail($data['visite_id']);

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

            // 3) Mettre la visite à encaisser
            $visite->update(['statut' => 'A_ENCAISSER']);

            // 4) Recalcule (sécurité)
            $facture->recalc();

            return $facture->fresh(['lignes','visite']);
        });

        return response()->json([
            'data' => $facture,
            'links' => [
                'self' => route('factures.show', $facture),
                'pdf'  => route('factures.pdf',  $facture),
            ]
        ], 201);
    }

    // GET /factures/{facture}/pdf
    public function pdf(Facture $facture)
    {
        $facture->load('lignes','visite.patient');
        // nécessite: composer require barryvdh/laravel-dompdf
        //$pdf = \PDF::loadView('factures.pdf', compact('facture'));
        //return $pdf->download($facture->numero . '.pdf');
    }
}
