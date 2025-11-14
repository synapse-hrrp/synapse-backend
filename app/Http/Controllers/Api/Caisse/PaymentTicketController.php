<?php

namespace App\Http\Controllers\Api\Caisse;

use App\Http\Controllers\Controller;
use App\Models\Reglement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentTicketController extends Controller
{
    /**
     * GET /api/v1/caisse/payments/{reglement}/ticket
     * PDF mini ticket (58/80mm) pour un règlement donné.
     */
    public function show(Request $request, Reglement $reglement): Response
    {
        // Charger ce qui est nécessaire au rendu
        $reglement->loadMissing([
            'facture:id,numero,devise,montant_total,montant_du,patient_id,statut,created_at',
            'facture.reglements:id,facture_id,montant,mode,reference,created_at',
            // ✅ champs patients réels
            'facture.patient:id,nom,prenom,telephone',
            'cashier:id,name,email',
            'cashSession:id,workstation,opened_at,closed_at',
        ]);

        $facture = $reglement->facture;

        // Totaux robustes (au cas où montant_paye n’existe pas en base)
        $total = (float) ($facture->montant_total ?? 0);
        $paid  = (float) optional($facture->reglements)->sum('montant');
        $due   = (float) ($facture->montant_du ?? max($total - $paid, 0));

        $meta = [
            'facility_name'    => config('app.name', 'Clinique'),
            'facility_address' => config('app.address', ''),   // optionnel dans config/app.php
            'facility_phone'   => config('app.phone', ''),     // optionnel dans config/app.php
            'workstation'      => $reglement->workstation ?? optional($reglement->cashSession)->workstation,
            'printed_at'       => now(),
            'printed_by'       => optional($request->user())->name,
        ];

        $pdf = Pdf::loadView('caisse.tickets.payment', [
            'reglement' => $reglement,
            'facture'   => $facture,
            'cashier'   => $reglement->cashier,
            'session'   => $reglement->cashSession,
            'meta'      => $meta,
            'total'     => $total,
            'paid'      => $paid,
            'due'       => $due,
        ]);

        // Largeur 80mm (≈ 226.77pt). Pour 58mm, utilise ~165pt.
        $pdf->setPaper([0, 0, 226.77, 841.89], 'portrait');

        $filename = 'ticket_' . $reglement->id . '.pdf';
        return $pdf->stream($filename); // ou ->download($filename)
    }
}
