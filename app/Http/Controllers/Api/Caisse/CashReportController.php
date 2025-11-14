<?php

namespace App\Http\Controllers\Api\Caisse;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Reglement;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashReportController extends Controller
{
    /**
     * GET /api/v1/caisse/payments
     * Liste paginée des paiements avec filtres.
     */
    public function payments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from'  => ['nullable','date'],
            'date_to'    => ['nullable','date'],
            'service_id' => ['nullable','integer'],
            'cashier_id' => ['nullable','integer'],
            'mode'       => ['nullable','string','max:20'],
            'workstation'=> ['nullable','string','max:50'],
            'search'     => ['nullable','string','max:100'],
            'sort'       => ['nullable', Rule::in(['date_asc','date_desc'])],
            'page'       => ['nullable','integer','min:1'],
            'per_page'   => ['nullable','integer','min:1','max:200'],
        ]);

        $perPage  = $validated['per_page'] ?? 20;
        $sort     = $validated['sort'] ?? 'date_desc';

        $q = Reglement::query()
            ->with([
                'facture:id,numero,devise,statut',
                'cashier:id,name',
                'cashSession:id,workstation',
            ]);

        // Filtres
        if (!empty($validated['service_id'])) {
            $q->where('service_id', $validated['service_id']);
        }

        if (!empty($validated['cashier_id'])) {
            $q->where('cashier_id', $validated['cashier_id']);
        }

        if (!empty($validated['mode'])) {
            $q->where('mode', $validated['mode']);
        }

        // ⇣ couvre workstation sur le règlement OU sur la session (comme exportCsv)
        if (!empty($validated['workstation'])) {
            $ws = $validated['workstation'];
            $q->where(function ($qq) use ($ws) {
                $qq->where('workstation', $ws)
                   ->orWhereHas('cashSession', fn($cs) => $cs->where('workstation', $ws));
            });
        }

        // Dates (par défaut: aujourd'hui)
        $dateFrom = !empty($validated['date_from']) ? Carbon::parse($validated['date_from'])->startOfDay() : Carbon::today();
        $dateTo   = !empty($validated['date_to'])   ? Carbon::parse($validated['date_to'])->endOfDay()   : Carbon::today()->endOfDay();
        $q->whereBetween('created_at', [$dateFrom, $dateTo]);

        // Recherche (numero de facture ou référence de paiement)
        if (!empty($validated['search'])) {
            $s = trim($validated['search']);
            $q->where(function($qq) use ($s) {
                $qq->where('reference','like',"%{$s}%")
                   ->orWhereHas('facture', fn($f) => $f->where('numero','like',"%{$s}%"));
            });
        }

        // Tri
        if ($sort === 'date_asc') {
            $q->orderBy('created_at','asc');
        } else {
            $q->orderBy('created_at','desc');
        }

        // Clones pour KPIs rapides
        $base        = (clone $q)->select('id','montant');
        $totalAmount = (float) (clone $base)->sum('montant');
        $count       = (int)   (clone $base)->count();
        $avg         = $count > 0 ? round($totalAmount / $count, 2) : 0.0;

        $page = $q->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'per_page'     => $page->perPage(),
                    'total'        => $page->total(),
                    'last_page'    => $page->lastPage(),
                ],
                'kpis' => [
                    'total_amount' => (float) $totalAmount,
                    'payments'     => (int) $count,
                    'avg_ticket'   => (float) $avg,
                    'date_from'    => $dateFrom->toDateString(),
                    'date_to'      => $dateTo->toDateString(),
                ],
            ],
        ], 200);
    }

    /**
     * GET /api/v1/caisse/rapport
     * KPIs + groupements + série temporelle.
     *
     * Params:
     * - period: day|week|month|range (default: day)
     * - date_from, date_to (si period=range)
     * - group_by[]: service|mode|cashier (facultatif, multi)
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period'    => ['nullable', Rule::in(['day','week','month','range'])],
            'date_from' => ['nullable','date'],
            'date_to'   => ['nullable','date'],
            'group_by'  => ['nullable','array'],
            'group_by.*'=> ['nullable', Rule::in(['service','mode','cashier'])],
        ]);

        $period = $validated['period'] ?? 'day';

        // Calcule la fenêtre temporelle
        [$dateFrom, $dateTo, $timeBucket] = $this->resolveWindow($period, $validated);

        $q = Reglement::query();

        $q->whereBetween('created_at', [$dateFrom, $dateTo]);

        // KPIs globaux
        $totals = (clone $q)->selectRaw('COUNT(*) as payments, COALESCE(SUM(montant),0) as total_amount')->first();
        $payments    = (int) ($totals->payments ?? 0);
        $totalAmount = (float) ($totals->total_amount ?? 0.0);
        $avgTicket   = $payments > 0 ? round($totalAmount / $payments, 2) : 0.0;

        // Série temporelle
        $series = (clone $q)
            ->selectRaw("DATE_FORMAT(created_at, ?) as bucket, COUNT(*) as payments, COALESCE(SUM(montant),0) as total_amount", [$timeBucket])
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        // Groupements demandés
        $groupsReq = $validated['group_by'] ?? [];
        $grouped   = [];

        // by_service (avec libellé)
        if (in_array('service', $groupsReq, true)) {
            $grouped['by_service'] = (clone $q)
                ->leftJoin('services as s', 'reglements.service_id', '=', 's.id')
                ->selectRaw('reglements.service_id, COALESCE(s.name, "n/a") as label, COUNT(*) as payments, COALESCE(SUM(montant),0) as total_amount')
                ->groupBy('reglements.service_id','s.name')
                ->orderByDesc('total_amount')
                ->limit(50)
                ->get();
        }

        // by_mode (ajoute label = mode)
        if (in_array('mode', $groupsReq, true)) {
            $grouped['by_mode'] = (clone $q)
                ->selectRaw('mode, mode as label, COUNT(*) as payments, COALESCE(SUM(montant),0) as total_amount')
                ->groupBy('mode')
                ->orderByDesc('total_amount')
                ->get();
        }

        // by_cashier (libellé caissier)
        if (in_array('cashier', $groupsReq, true)) {
            $grouped['by_cashier'] = (clone $q)
                ->leftJoin('users as u', 'reglements.cashier_id', '=', 'u.id')
                ->selectRaw('reglements.cashier_id, COALESCE(u.name, "n/a") as label, COUNT(*) as payments, COALESCE(SUM(montant),0) as total_amount')
                ->groupBy('reglements.cashier_id','u.name')
                ->orderByDesc('total_amount')
                ->limit(50)
                ->get();
        }

        return response()->json([
            'meta' => [
                'period'    => $period,
                'date_from' => $dateFrom->toDateString(),
                'date_to'   => $dateTo->toDateString(),
            ],
            'kpis' => [
                'total_amount' => (float) $totalAmount,
                'payments'     => (int) $payments,
                'avg_ticket'   => (float) $avgTicket,
            ],
            'series' => $series,
            'groups' => $grouped,
        ], 200);
    }

    /**
     * Détermine la fenêtre (from/to) et le format de bucket pour la série temporelle.
     */
    private function resolveWindow(string $period, array $validated): array
    {
        $today = Carbon::today();

        switch ($period) {
            case 'week':
                $from   = (clone $today)->startOfWeek();
                $to     = (clone $today)->endOfWeek();
                $bucket = '%Y-%m-%d';
                break;

            case 'month':
                $from   = (clone $today)->startOfMonth();
                $to     = (clone $today)->endOfMonth();
                $bucket = '%Y-%m-%d';
                break;

            case 'range':
                $from = !empty($validated['date_from'])
                    ? Carbon::parse($validated['date_from'])->startOfDay()
                    : (clone $today)->startOfDay();

                $to   = !empty($validated['date_to'])
                    ? Carbon::parse($validated['date_to'])->endOfDay()
                    : (clone $today)->endOfDay();

                // Si l’intervalle est court, bucket horaire; sinon journalier
                $diffDays = $from->diffInDays($to);
                $bucket   = $diffDays <= 2 ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d';
                break;

            case 'day':
            default:
                $from   = (clone $today)->startOfDay();
                $to     = (clone $today)->endOfDay();
                $bucket = '%Y-%m-%d %H:00:00';
                break;
        }

        return [$from, $to, $bucket];
    }

    /**
     * GET /api/v1/caisse/payments/export
     * Export CSV des paiements (UTF-8 BOM pour Excel).
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'date_from'  => ['nullable','date'],
            'date_to'    => ['nullable','date'],
            'service_id' => ['nullable','integer'],
            'cashier_id' => ['nullable','integer'],
            'mode'       => ['nullable','string','max:20'],
            'workstation'=> ['nullable','string','max:50'],
            'search'     => ['nullable','string','max:100'],
            'sort'       => ['nullable', Rule::in(['date_asc','date_desc'])],
        ]);

        $sort = $validated['sort'] ?? 'date_desc';

        $q = Reglement::query()
            ->with([
                'facture:id,numero,devise,statut',
                'cashier:id,name,email',
                'cashSession:id,workstation',
            ]);

        if (!empty($validated['service_id'])) $q->where('service_id', $validated['service_id']);
        if (!empty($validated['cashier_id'])) $q->where('cashier_id', $validated['cashier_id']);
        if (!empty($validated['mode']))       $q->where('mode', $validated['mode']);

        // ⇣ couvre workstation sur le règlement OU sur la session
        if (!empty($validated['workstation'])) {
            $ws = $validated['workstation'];
            $q->where(function($qq) use ($ws) {
                $qq->where('workstation', $ws)
                   ->orWhereHas('cashSession', fn($cs) => $cs->where('workstation', $ws));
            });
        }

        $dateFrom = !empty($validated['date_from']) ? Carbon::parse($validated['date_from'])->startOfDay() : Carbon::today();
        $dateTo   = !empty($validated['date_to'])   ? Carbon::parse($validated['date_to'])->endOfDay()   : Carbon::today()->endOfDay();
        $q->whereBetween('created_at', [$dateFrom, $dateTo]);

        if (!empty($validated['search'])) {
            $s = trim($validated['search']);
            $q->where(function($qq) use ($s) {
                $qq->where('reference','like',"%{$s}%")
                   ->orWhereHas('facture', fn($f) => $f->where('numero','like',"%{$s}%"));
            });
        }

        $sort === 'date_asc' ? $q->orderBy('created_at','asc') : $q->orderBy('created_at','desc');

        $filename = 'cash_payments_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 (Excel-friendly)
            fwrite($out, "\xEF\xBB\xBF");

            // entêtes
            fputcsv($out, [
                'id','created_at','montant','mode','reference','devise',
                'facture_id','facture_numero','facture_statut',
                'cashier_id','cashier_name','cashier_email',
                'cash_session_id','workstation','service_id'
            ]);

            // petite fonction anti CSV-injection
            $sanitize = function ($v) {
                if (!is_string($v)) return $v;
                return preg_match('/^[=+\-@]/', $v) ? "'".$v : $v;
            };

            // chunk stable
            $q->select('reglements.*')   // s’assure que l’ID est dans la sélection
              ->orderBy('id')            // ordre stable pour chunkById
              ->chunkById(1000, function ($rows) use ($out, $sanitize) {
                  foreach ($rows as $r) {
                      fputcsv($out, [
                          $r->id,
                          optional($r->created_at)->toDateTimeString(),
                          (float)$r->montant,
                          $sanitize($r->mode),
                          $sanitize($r->reference),
                          $sanitize($r->devise),
                          $r->facture_id,
                          $sanitize(optional($r->facture)->numero),
                          $sanitize(optional($r->facture)->statut),
                          $r->cashier_id,
                          $sanitize(optional($r->cashier)->name),
                          $sanitize(optional($r->cashier)->email),
                          $r->cash_session_id,
                          $sanitize($r->workstation ?: optional($r->cashSession)->workstation),
                          $r->service_id,
                      ]);
                  }
              });

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }
}