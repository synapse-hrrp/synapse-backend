<?php

namespace App\Http\Controllers\Api\Caisse;

use App\Http\Controllers\Controller;
use App\Models\Reglement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CashTopController extends Controller
{
    /**
     * GET /api/v1/caisse/top/services
     */
    public function topServices(Request $request): JsonResponse
    {
        [$from, $to] = $this->windowFromRequest($request);

        $validated = $request->validate([
            'limit'       => ['nullable','integer','min:1','max:100'],
            'mode'        => ['nullable','string','max:20'],
            'workstation' => ['nullable','string','max:50'],
            'include_na'  => ['nullable','boolean'], // ⬅ option pour masquer les NULL
        ]);

        $limit     = (int) ($validated['limit'] ?? 10);
        $includeNa = filter_var($request->input('include_na', true), FILTER_VALIDATE_BOOLEAN);

        $q = Reglement::query()
            ->leftJoin('services as s', 'reglements.service_id', '=', 's.id')
            ->whereBetween('reglements.created_at', [$from, $to]);

        if ($request->filled('mode')) {
            $q->where('reglements.mode', $request->string('mode'));
        }

        if ($request->filled('workstation')) {
            $ws = $request->string('workstation');
            $q->where(function ($qq) use ($ws) {
                $qq->where('reglements.workstation', $ws)
                   ->orWhereHas('cashSession', fn($cs) => $cs->where('workstation', $ws));
            });
        }

        if (! $includeNa) {
            $q->whereNotNull('reglements.service_id');
        }

        $rows = $q->selectRaw(
                'reglements.service_id as id, '.
                'COALESCE(s.name, "n/a") as label, '.
                'COUNT(*) as payments, '.
                'COALESCE(SUM(reglements.montant),0) as total_amount, '.
                'COALESCE(AVG(reglements.montant),0) as avg_ticket'
            )
            ->groupBy('reglements.service_id','s.name')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->map(function ($r) {
                $r->total_amount = (float) $r->total_amount;
                $r->avg_ticket   = round((float) $r->avg_ticket, 2);
                return $r;
            });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'period'     => $this->periodLabel($request),
                'date_from'  => $from->toDateString(),
                'date_to'    => $to->toDateString(),
                'limit'      => $limit,
                'filters'    => [
                    'mode'        => $request->input('mode'),
                    'workstation' => $request->input('workstation'),
                    'include_na'  => $includeNa,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/caisse/top/cashiers
     */
    public function topCashiers(Request $request): JsonResponse
    {
        [$from, $to] = $this->windowFromRequest($request);

        $validated = $request->validate([
            'limit'       => ['nullable','integer','min:1','max:100'],
            'service_id'  => ['nullable','integer'],
            'mode'        => ['nullable','string','max:20'],
            'workstation' => ['nullable','string','max:50'],
            'include_na'  => ['nullable','boolean'], // ⬅ option pour masquer les NULL
        ]);

        $limit     = (int) ($validated['limit'] ?? 10);
        $includeNa = filter_var($request->input('include_na', true), FILTER_VALIDATE_BOOLEAN);

        $q = Reglement::query()
            ->leftJoin('users as u', 'reglements.cashier_id', '=', 'u.id')
            ->whereBetween('reglements.created_at', [$from, $to]);

        if ($request->filled('service_id')) {
            $q->where('reglements.service_id', (int) $request->input('service_id'));
        }
        if ($request->filled('mode')) {
            $q->where('reglements.mode', $request->string('mode'));
        }
        if ($request->filled('workstation')) {
            $ws = $request->string('workstation');
            $q->where(function ($qq) use ($ws) {
                $qq->where('reglements.workstation', $ws)
                   ->orWhereHas('cashSession', fn($cs) => $cs->where('workstation', $ws));
            });
        }

        if (! $includeNa) {
            $q->whereNotNull('reglements.cashier_id');
        }

        $rows = $q->selectRaw(
                'reglements.cashier_id as id, '.
                'COALESCE(u.name, "n/a") as label, '.
                'COUNT(*) as payments, '.
                'COALESCE(SUM(reglements.montant),0) as total_amount, '.
                'COALESCE(AVG(reglements.montant),0) as avg_ticket'
            )
            ->groupBy('reglements.cashier_id','u.name')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->map(function ($r) {
                $r->total_amount = (float) $r->total_amount;
                $r->avg_ticket   = round((float) $r->avg_ticket, 2);
                return $r;
            });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'period'     => $this->periodLabel($request),
                'date_from'  => $from->toDateString(),
                'date_to'    => $to->toDateString(),
                'limit'      => (string) $limit,
                'filters'    => [
                    'service_id'  => $request->input('service_id'),
                    'mode'        => $request->input('mode'),
                    'workstation' => $request->input('workstation'),
                    'include_na'  => $includeNa,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/caisse/top/overview
     * Combine top services & top cashiers
     */
    public function overview(Request $request): JsonResponse
    {
        [$from, $to] = $this->windowFromRequest($request);

        $validated = $request->validate([
            'limit_services' => ['nullable','integer','min:1','max:100'],
            'limit_cashiers' => ['nullable','integer','min:1','max:100'],
            'service_id'     => ['nullable','integer'],
            'mode'           => ['nullable','string','max:20'],
            'workstation'    => ['nullable','string','max:50'],
            'include_na'     => ['nullable','boolean'],
        ]);

        $includeNa     = filter_var($request->input('include_na', true), FILTER_VALIDATE_BOOLEAN);
        $limitServices = (int) ($validated['limit_services'] ?? 10);
        $limitCashiers = (int) ($validated['limit_cashiers'] ?? 10);

        $services = $this->buildTopServices($request, $from, $to, $limitServices, $includeNa);
        $cashiers = $this->buildTopCashiers($request, $from, $to, $limitCashiers, $includeNa);

        return response()->json([
            'data' => [
                'services' => $services,
                'cashiers' => $cashiers,
            ],
            'meta' => [
                'period'          => $this->periodLabel($request),
                'date_from'       => $from->toDateString(),
                'date_to'         => $to->toDateString(),
                'limit_services'  => $limitServices,
                'limit_cashiers'  => $limitCashiers,
                'filters' => [
                    'service_id'  => $request->input('service_id'),
                    'mode'        => $request->input('mode'),
                    'workstation' => $request->input('workstation'),
                    'include_na'  => $includeNa,
                ],
            ],
        ]);
    }

    /* =========================
       Helpers
    ==========================*/

    /**
     * Calcule la fenêtre temporelle à partir des paramètres:
     * - period: day|week|month|year|range  (default: day)
     * - on: date d’ancrage (optionnelle) pour day/week/month/year (ex: on=2025-11-09)
     * - date_from/date_to: utilisés si period=range (ou si fournis explicitement)
     */
    private function windowFromRequest(Request $request): array
    {
        $request->validate([
            'period'    => ['nullable', Rule::in(['day','week','month','year','range'])],
            'on'        => ['nullable','date'],
            'date_from' => ['nullable','date'],
            'date_to'   => ['nullable','date'],
        ]);

        $period = $request->input('period', 'day');
        $on     = $request->filled('on')
            ? Carbon::parse($request->input('on'))->startOfDay()
            : Carbon::today();

        // Si l’utilisateur donne date_from / date_to, on les respecte en priorité
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $from = $request->filled('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : (clone $on)->startOfDay();
            $to   = $request->filled('date_to')   ? Carbon::parse($request->input('date_to'))->endOfDay()     : (clone $on)->endOfDay();
            return [$from, $to];
        }

        // Sinon, on applique le period
        switch ($period) {
            case 'week':
                $from = (clone $on)->startOfWeek();
                $to   = (clone $on)->endOfWeek();
                break;
            case 'month':
                $from = (clone $on)->startOfMonth();
                $to   = (clone $on)->endOfMonth();
                break;
            case 'year':
                $from = (clone $on)->startOfYear();
                $to   = (clone $on)->endOfYear();
                break;
            case 'range':
                // fallback: si pas de from/to fournis, on traite comme 'day'
                $from = (clone $on)->startOfDay();
                $to   = (clone $on)->endOfDay();
                break;
            case 'day':
            default:
                $from = (clone $on)->startOfDay();
                $to   = (clone $on)->endOfDay();
                break;
        }

        return [$from, $to];
    }

    private function periodLabel(Request $request): string
    {
        return (string) $request->input('period', 'day');
    }

    private function baseTopQuery(Request $request, Carbon $from, Carbon $to)
    {
        $q = Reglement::query()->whereBetween('reglements.created_at', [$from, $to]);

        if ($request->filled('service_id')) {
            $q->where('reglements.service_id', (int) $request->input('service_id'));
        }
        if ($request->filled('mode')) {
            $q->where('reglements.mode', $request->string('mode'));
        }
        if ($request->filled('workstation')) {
            $ws = $request->string('workstation');
            $q->where(function ($qq) use ($ws) {
                $qq->where('reglements.workstation', $ws)
                   ->orWhereHas('cashSession', fn($cs) => $cs->where('workstation', $ws));
            });
        }

        return $q;
    }

    private function buildTopServices(Request $request, Carbon $from, Carbon $to, int $limit, bool $includeNa = true)
    {
        $q = $this->baseTopQuery($request, $from, $to)
            ->leftJoin('services as s', 'reglements.service_id', '=', 's.id');

        if (! $includeNa) {
            $q->whereNotNull('reglements.service_id');
        }

        return $q->selectRaw(
                'reglements.service_id as id, '.
                'COALESCE(s.name, "n/a") as label, '.
                'COUNT(*) as payments, '.
                'COALESCE(SUM(reglements.montant),0) as total_amount, '.
                'COALESCE(AVG(reglements.montant),0) as avg_ticket'
            )
            ->groupBy('reglements.service_id','s.name')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->map(function ($r) {
                $r->total_amount = (float) $r->total_amount;
                $r->avg_ticket   = round((float) $r->avg_ticket, 2);
                return $r;
            });
    }

    private function buildTopCashiers(Request $request, Carbon $from, Carbon $to, int $limit, bool $includeNa = true)
    {
        $q = $this->baseTopQuery($request, $from, $to)
            ->leftJoin('users as u', 'reglements.cashier_id', '=', 'u.id');

        if (! $includeNa) {
            $q->whereNotNull('reglements.cashier_id');
        }

        return $q->selectRaw(
                'reglements.cashier_id as id, '.
                'COALESCE(u.name, "n/a") as label, '.
                'COUNT(*) as payments, '.
                'COALESCE(SUM(reglements.montant),0) as total_amount, '.
                'COALESCE(AVG(reglements.montant),0) as avg_ticket'
            )
            ->groupBy('reglements.cashier_id','u.name')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->map(function ($r) {
                $r->total_amount = (float) $r->total_amount;
                $r->avg_ticket   = round((float) $r->avg_ticket, 2);
                return $r;
            });
    }
}