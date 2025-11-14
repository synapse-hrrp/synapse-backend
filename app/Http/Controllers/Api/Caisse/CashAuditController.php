<?php

namespace App\Http\Controllers\Api\Caisse;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use App\Models\Caisse\CashRegisterAudit;
use Symfony\Component\HttpFoundation\StreamedResponse;


class CashAuditController extends Controller
{
    /**
     * GET /api/v1/caisse/audit
     * Journal des événements de caisse (paginé + filtres).
     *
     * Query params optionnels:
     * - date_from, date_to (dates)
     * - event: SESSION_OPENED|PAYMENT_CREATED|SESSION_CLOSED
     * - session_id, user_id, facture_id, reglement_id (ints)
     * - workstation (string), ip (string)
     * - search (string): recherche dans payload JSON, ip, workstation
     * - sort: date_asc|date_desc (default: date_desc)
     * - per_page (1..200, default: 20)
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from'    => ['nullable','date'],
            'date_to'      => ['nullable','date'],
            'event'        => ['nullable', Rule::in([
                CashRegisterAudit::SESSION_OPENED,
                CashRegisterAudit::PAYMENT_CREATED,
                CashRegisterAudit::SESSION_CLOSED,
            ])],
            'session_id'   => ['nullable','integer'],
            'user_id'      => ['nullable','integer'],
            'facture_id'   => ['nullable','integer'],
            'reglement_id' => ['nullable','integer'],
            'workstation'  => ['nullable','string','max:50'],
            'ip'           => ['nullable','string','max:64'],
            'search'       => ['nullable','string','max:100'],
            'sort'         => ['nullable', Rule::in(['date_asc','date_desc'])],
            'per_page'     => ['nullable','integer','min:1','max:200'],
        ]);

        $perPage = $validated['per_page'] ?? 20;
        $sort    = $validated['sort'] ?? 'date_desc';

        $q = CashRegisterAudit::query()
            ->with([
                'session:id,workstation',
                'user:id,name,email',
                'reglement:id,montant,mode,facture_id',
                'facture:id,numero,devise',
            ]);

        // Fenêtre temporelle (par défaut: aujourd’hui)
        $dateFrom = !empty($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : Carbon::today()->startOfDay();

        $dateTo = !empty($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : Carbon::today()->endOfDay();

        $q->whereBetween('created_at', [$dateFrom, $dateTo]);

        // Filtres simples
        foreach (['event','session_id','user_id','facture_id','reglement_id'] as $field) {
            if (!empty($validated[$field])) {
                $q->where($field, $validated[$field]);
            }
        }

        if (!empty($validated['workstation'])) {
            $q->where('workstation', $validated['workstation']);
        }

        if (!empty($validated['ip'])) {
            $q->where('ip', 'like', '%'.trim($validated['ip']).'%');
        }

        // Recherche libre (payload/ip/workstation)
        if (!empty($validated['search'])) {
            $s = trim($validated['search']);
            $q->where(function ($qq) use ($s) {
                // ip / workstation
                $qq->where('ip', 'like', "%{$s}%")
                   ->orWhere('workstation', 'like', "%{$s}%");

                // payload JSON (selon driver, JSON_SEARCH non-portable -> LIKE)
                $qq->orWhere('payload', 'like', "%{$s}%");
            });
        }

        // Tri
        $sort === 'date_asc'
            ? $q->orderBy('created_at', 'asc')
            : $q->orderBy('created_at', 'desc');

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
                'window' => [
                    'date_from' => $dateFrom->toDateString(),
                    'date_to'   => $dateTo->toDateString(),
                ],
            ],
        ], 200);
    }
    
    public function exportCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'date_from'    => ['nullable','date'],
            'date_to'      => ['nullable','date'],
            'event'        => ['nullable', Rule::in([
                CashRegisterAudit::SESSION_OPENED,
                CashRegisterAudit::PAYMENT_CREATED,
                CashRegisterAudit::SESSION_CLOSED,
            ])],
            'session_id'   => ['nullable','integer'],
            'user_id'      => ['nullable','integer'],
            'facture_id'   => ['nullable','integer'],
            'reglement_id' => ['nullable','integer'],
            'workstation'  => ['nullable','string','max:50'],
            'ip'           => ['nullable','string','max:64'],
            'search'       => ['nullable','string','max:100'],
            'sort'         => ['nullable', Rule::in(['date_asc','date_desc'])],
        ]);

        $dateFrom = !empty($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : Carbon::today()->startOfDay();

        $dateTo = !empty($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : Carbon::today()->endOfDay();

        $q = CashRegisterAudit::query()
            ->with([
                'session:id,workstation',
                'user:id,name,email',
                'reglement:id,montant,mode,devise,facture_id',
                'facture:id,numero,devise',
            ])
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        foreach (['event','session_id','user_id','facture_id','reglement_id'] as $field) {
            if (!empty($validated[$field])) {
                $q->where($field, $validated[$field]);
            }
        }
        if (!empty($validated['workstation'])) $q->where('workstation', $validated['workstation']);
        if (!empty($validated['ip']))          $q->where('ip', 'like', '%'.trim($validated['ip']).'%');

        if (!empty($validated['search'])) {
            $s = trim($validated['search']);
            $q->where(function ($qq) use ($s) {
                $qq->where('ip', 'like', "%{$s}%")
                ->orWhere('workstation', 'like', "%{$s}%")
                ->orWhere('payload', 'like', "%{$s}%");
            });
        }

        ($validated['sort'] ?? 'date_desc') === 'date_asc'
            ? $q->orderBy('created_at','asc')
            : $q->orderBy('created_at','desc');

        $filename = 'cash_audit_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'id','created_at','event','workstation','ip',
                'user_id','user_name','user_email',
                'session_id',
                'facture_id','facture_numero','facture_devise',
                'reglement_id','reglement_montant','reglement_mode','reglement_devise',
                'payload_json'
            ]);

            $q->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->id,
                        optional($row->created_at)->toDateTimeString(),
                        $row->event,
                        $row->workstation,
                        $row->ip,
                        $row->user_id,
                        optional($row->user)->name,
                        optional($row->user)->email,
                        $row->session_id,
                        $row->facture_id,
                        optional($row->facture)->numero,
                        optional($row->facture)->devise,
                        $row->reglement_id,
                        optional($row->reglement)->montant,
                        optional($row->reglement)->mode,
                        optional($row->reglement)->devise,
                        $row->payload ? json_encode($row->payload, JSON_UNESCAPED_UNICODE) : null,
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
