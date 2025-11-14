<?php

namespace App\Http\Controllers\Api\Caisse;

use App\Http\Controllers\Controller;
use App\Models\Reglement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class CashZReportController extends Controller
{
    /**
     * GET /api/v1/caisse/z-report
     * Retourne le Z-Report (JSON)
     */
    public function json(Request $request)
    {
        $validated = $request->validate([
            'date'        => ['nullable', 'date'],
            'workstation' => ['nullable', 'string', 'max:50'],
            'service_id'  => ['nullable', 'integer'],
            'cashier_id'  => ['nullable', 'integer'],
            'mode'        => ['nullable', 'string', 'max:20'],
        ]);

        $day  = $validated['date'] ? Carbon::parse($validated['date']) : Carbon::today();
        $from = (clone $day)->startOfDay();
        $to   = (clone $day)->endOfDay();

        $q = Reglement::query()->whereBetween('created_at', [$from, $to]);

        if (!empty($validated['workstation'])) {
            $ws = $validated['workstation'];
            $q->where(function ($qq) use ($ws) {
                $qq->where('workstation', $ws)
                    ->orWhereHas('cashSession', fn($cs) => $cs->where('workstation', $ws));
            });
        }

        if (!empty($validated['service_id'])) $q->where('service_id', $validated['service_id']);
        if (!empty($validated['cashier_id'])) $q->where('cashier_id', $validated['cashier_id']);
        if (!empty($validated['mode'])) $q->where('mode', $validated['mode']);

        $rows = (clone $q)->with(['cashier:id,name', 'cashSession:id,workstation'])
            ->orderBy('created_at')
            ->get(['id', 'montant', 'mode', 'reference', 'devise', 'cashier_id', 'cash_session_id', 'created_at', 'service_id', 'workstation']);

        $total = (float) (clone $q)->sum('montant');
        $count = (int)   (clone $q)->count();
        $avg   = $count ? round($total / $count, 2) : 0.0;

        return response()->json([
            'meta' => [
                'date'        => $day->toDateString(),
                'workstation' => $validated['workstation'] ?? null,
                'filters'     => [
                    'service_id' => $validated['service_id'] ?? null,
                    'cashier_id' => $validated['cashier_id'] ?? null,
                    'mode'       => $validated['mode'] ?? null,
                ],
            ],
            'kpis' => [
                'payments'     => $count,
                'total_amount' => $total,
                'avg_ticket'   => $avg,
            ],
            'data' => $rows,
        ], 200);
    }

    /**
     * GET /api/v1/caisse/z-report/pdf
     * PDF du Z-report (clôture journalière)
     */
    public function pdf(Request $request)
    {
        // Récupère le JSON du rapport via la même méthode
        $json = $this->json($request)->getData(true);

        $pdf = Pdf::loadView('caisse.tickets.z-report', $json);
        $pdf->setPaper([0, 0, 226.77, 1000], 'portrait'); // format ticket (80mm)

        return $pdf->stream('zreport_' . $json['meta']['date'] . '.pdf');
    }
}
