<?php

namespace App\Http\Controllers\Api\Caisse;

use App\Http\Controllers\Controller;
use App\Models\Caisse\CashRegisterSession;
use App\Models\Caisse\CashRegisterAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashSessionAdminController extends Controller
{
    /**
     * POST /api/v1/caisse/sessions/{session}/force-close
     * Ferme une session restée ouverte (action admin).
     */
    public function forceClose(Request $request, CashRegisterSession $session): JsonResponse
    {
        if ($session->closed_at) {
            throw ValidationException::withMessages([
                'session' => 'Cette session est déjà fermée.',
            ]);
        }

        DB::transaction(function () use ($request, $session) {
            $session->closing_note = 'Force close by admin';
            $session->closed_at    = now();
            $session->save();

            CashRegisterAudit::create([
                'session_id'  => $session->id,
                'user_id'     => $request->user()?->id,
                'event'       => CashRegisterAudit::SESSION_CLOSED,
                'payload'     => [
                    'payments_count' => (int) ($session->payments_count ?? 0),
                    'total_amount'   => (float) ($session->total_amount ?? 0),
                    'force'          => true,
                    'closing_note'   => $session->closing_note,
                ],
                'workstation' => $session->workstation,
                'ip'          => $request->ip(),
            ]);
        });

        return response()->json([
            'data' => [
                'id'             => $session->id,
                'user_id'        => $session->user_id,
                'service_id'     => $session->service_id,
                'currency'       => $session->currency,
                'workstation'    => $session->workstation,
                'opened_at'      => $session->opened_at?->toIso8601String(),
                'closed_at'      => $session->closed_at?->toIso8601String(),
                'payments_count' => (int) ($session->payments_count ?? 0),
                'total_amount'   => (float) ($session->total_amount ?? 0),
                'closing_note'   => $session->closing_note,
            ]
        ], 200);
    }
}
