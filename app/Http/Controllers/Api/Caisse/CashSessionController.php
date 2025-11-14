<?php

namespace App\Http\Controllers\Api\Caisse;

use App\Http\Controllers\Controller;
use App\Models\Caisse\CashRegisterSession;
use App\Models\Caisse\CashRegisterAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashSessionController extends Controller
{
    /**
     * Ouvrir une session de caisse.
     * Requiert le header X-Workstation. service_id est optionnel (session générale si null).
     */
    public function open(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'currency'      => ['nullable','string','max:3'],
            'service_id'    => ['nullable','integer'], // adapte en 'uuid' si tes services sont en UUID
            'opening_note'  => ['nullable','string','max:500'],
        ]);

        $workstation = $request->header('X-Workstation');
        if (! $workstation) {
            throw ValidationException::withMessages([
                'workstation' => 'Le header X-Workstation est requis pour ouvrir une session.',
            ]);
        }

        // Unicité : 1 session ouverte max par (user_id, workstation)
        $already = CashRegisterSession::query()
            ->where('user_id', $user->id)
            ->where('workstation', $workstation)
            ->whereNull('closed_at')
            ->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'session' => "Une session est déjà ouverte sur ce poste par cet utilisateur.",
            ]);
        }

        $session = DB::transaction(function () use ($request, $user, $workstation, $validated) {

            $session = CashRegisterSession::create([
                'user_id'       => $user->id,
                'workstation'   => $workstation,
                'service_id'    => $validated['service_id'] ?? null,   // null = session générale
                'currency'      => $validated['currency'] ?? 'XAF',
                'opened_at'     => now(),
                'opening_note'  => $validated['opening_note'] ?? null,
                // agrégats init
                'payments_count'=> 0,
                'total_amount'  => 0,
            ]);

            // Audit via helper
            CashRegisterAudit::log(
                'SESSION_OPENED',
                $session,
                $user,
                [
                    'service_id'   => $session->service_id,
                    'currency'     => $session->currency,
                    'opening_note' => $session->opening_note,
                ],
                [
                    'ip'          => $request->ip(),
                    'workstation' => $workstation,
                ]
            );

            return $session;
        });

        return response()->json([
            'data' => [
                'id'          => $session->id,
                'user_id'     => $session->user_id,
                'service_id'  => $session->service_id,
                'currency'    => $session->currency,
                'workstation' => $session->workstation,
                'opened_at'   => $session->opened_at?->toIso8601String(),
                'payments_count' => (int) ($session->payments_count ?? 0),
                'total_amount'   => (float) ($session->total_amount ?? 0),
            ]
        ], 201);
    }

    /**
     * Récupérer la session ouverte de l’utilisateur (sur le poste courant si X-Workstation est fourni).
     */
    public function current(Request $request): JsonResponse
    {
        $user        = $request->user();
        $workstation = $request->header('X-Workstation');

        $query = CashRegisterSession::query()
            ->where('user_id', $user->id)
            ->whereNull('closed_at');

        if ($workstation) {
            $query->where('workstation', $workstation);
        }

        /** @var CashRegisterSession|null $session */
        $session = $query->latest('opened_at')->first();

        if (! $session) {
            return response()->json(['data' => null], 200);
        }

        return response()->json([
            'data' => [
                'id'             => $session->id,
                'user_id'        => $session->user_id,
                'service_id'     => $session->service_id,
                'currency'       => $session->currency,
                'workstation'    => $session->workstation,
                'opened_at'      => $session->opened_at?->toIso8601String(),
                'payments_count' => (int) ($session->payments_count ?? 0),
                'total_amount'   => (float) ($session->total_amount ?? 0),
            ]
        ]);
    }

    /**
     * Fermer la session ouverte (sur le poste courant si X-Workstation est fourni).
     */
    public function close(Request $request): JsonResponse
    {
        $user        = $request->user();
        $workstation = $request->header('X-Workstation');

        $validated = $request->validate([
            'closing_note' => ['nullable','string','max:500'],
        ]);

        $query = CashRegisterSession::query()
            ->where('user_id', $user->id)
            ->whereNull('closed_at');

        if ($workstation) {
            $query->where('workstation', $workstation);
        }

        /** @var CashRegisterSession|null $session */
        $session = $query->latest('opened_at')->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'session' => "Aucune session ouverte à fermer pour cet utilisateur" . ($workstation ? " sur ce poste." : "."),
            ]);
        }

        DB::transaction(function () use ($request, $session, $validated) {
            $session->closing_note = $validated['closing_note'] ?? null;
            $session->closed_at    = now();
            $session->save();

            // Audit via helper
            CashRegisterAudit::log(
                'SESSION_CLOSED',
                $session,
                $request->user(),
                [
                    'payments_count' => (int) ($session->payments_count ?? 0),
                    'total_amount'   => (float) ($session->total_amount ?? 0),
                    'closing_note'   => $session->closing_note,
                ],
                [
                    'ip'          => $request->ip(),
                    'workstation' => $session->workstation,
                ]
            );
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
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user        = $request->user();
        $workstation = $request->header('X-Workstation');

        $q = \App\Models\Caisse\CashRegisterSession::query()
            ->where('user_id', $user->id)
            ->whereNull('closed_at');

        if ($workstation) {
            $q->where('workstation', $workstation);
        }

        /** @var \App\Models\Caisse\CashRegisterSession|null $session */
        $session = $q->latest('opened_at')->first();

        if (! $session) {
            return response()->json(['data' => null], 200);
        }

        // KPIs en lecture directe des agrégats tenus à jour par ReglementController
        $paymentsCount = (int) ($session->payments_count ?? 0);
        $totalAmount   = (float) ($session->total_amount ?? 0);

        return response()->json([
            'data' => [
                'id'             => $session->id,
                'user_id'        => $session->user_id,
                'service_id'     => $session->service_id,
                'currency'       => $session->currency,
                'workstation'    => $session->workstation,
                'opened_at'      => $session->opened_at?->toIso8601String(),
                'payments_count' => $paymentsCount,
                'total_amount'   => $totalAmount,
            ],
        ], 200);
    }
    

}
