<?php 

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Facture, Reglement};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{DB, Cache};
use Illuminate\Validation\ValidationException;
use App\Models\Caisse\CashRegisterSession;
use App\Models\Caisse\CashRegisterAudit;

class ReglementController extends Controller
{
    public function store(Request $request, Facture $facture): JsonResponse
    {
        $validated = $request->validate([
            'montant'   => ['required','numeric','min:0.01'],
            'mode'      => ['required','string','max:20'],
            'reference' => ['nullable','string','max:100'],
            // on ignore ici un éventuel service_id envoyé par le front pour les caissier_service
        ]);

        // Idempotency (facultatif)
        $key = $request->header('Idempotency-Key');
        if ($key && Cache::has("reglement:$key")) {
            return response()->json(Cache::get("reglement:$key"), 200);
        }

        $payload = DB::transaction(function () use ($facture, $validated, $request) {

            // Recalcule avant et vérifie le reste dû
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

            // --------- UTILISATEUR & ROLES ----------
            $cashier = $request->user();
            if (!$cashier) {
                throw ValidationException::withMessages([
                    'user' => 'Utilisateur non authentifié.',
                ]);
            }

            // on s’assure d’avoir les relations nécessaires
            $cashier->loadMissing(['roles', 'personnel', 'services']);

            $roleNames = $cashier->roles
                ->pluck('name')
                ->map(fn($n) => strtolower($n))
                ->all();

            $isCaisseGeneral = in_array('caissier_general', $roleNames, true)
                || in_array('admin_caisse', $roleNames, true)
                || in_array('admin', $roleNames, true);

            $isCaisseService = in_array('caissier_service', $roleNames, true);

            // --------- SERVICES AUTORISÉS ----------
            $allowedServiceIds = [];

            // services via pivot user_service
            $pivotIds = $cashier->services
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();

            $allowedServiceIds = array_merge($allowedServiceIds, $pivotIds);

            // fallback : service de la fiche personnel
            if ($cashier->personnel && $cashier->personnel->service_id) {
                $allowedServiceIds[] = (int) $cashier->personnel->service_id;
            }

            $allowedServiceIds = array_values(array_unique($allowedServiceIds));

            // --------- SERVICE DE LA FACTURE ----------
            $facture->loadMissing('visite');
            $factureServiceId = $facture->visite?->service_id
                ? (int) $facture->visite->service_id
                : null;

            // éventuel service_id venant du front (pour les cas généraux)
            $requestedServiceId = $request->input('service_id');
            $requestedServiceId = $requestedServiceId ? (int) $requestedServiceId : null;

            // --------- DÉTERMINATION DU SERVICE A UTILISER POUR CE PAIEMENT ----------
            $paymentServiceId = null;

            if ($isCaisseGeneral) {
                // caisse générale / admin caisse / admin -> accès global
                if ($requestedServiceId) {
                    $paymentServiceId = $requestedServiceId;
                } elseif ($factureServiceId) {
                    $paymentServiceId = $factureServiceId;
                } else {
                    // aucune info : on laisse null, encaissement quand même possible
                    $paymentServiceId = null;
                }
            } elseif ($isCaisseService) {
                // CAISSIER SERVICE : doit rester dans son périmètre

                if (empty($allowedServiceIds)) {
                    throw ValidationException::withMessages([
                        'service' => 'Aucun service n\'est affecté à votre compte. Contactez l\'administrateur.',
                    ]);
                }

                // 1) priorité au service de la facture
                if ($factureServiceId && in_array($factureServiceId, $allowedServiceIds, true)) {
                    $paymentServiceId = $factureServiceId;
                }
                // 2) sinon, si le front envoie explicitement un service_id autorisé
                elseif ($requestedServiceId && in_array($requestedServiceId, $allowedServiceIds, true)) {
                    $paymentServiceId = $requestedServiceId;
                }
                // 3) sinon -> refus
                else {
                    throw ValidationException::withMessages([
                        'service' => 'Service non autorisé pour cet utilisateur.',
                    ]);
                }
            } else {
                // autres rôles ayant un accès exceptionnel à la caisse (rare)
                if ($requestedServiceId) {
                    $paymentServiceId = $requestedServiceId;
                } elseif ($factureServiceId) {
                    $paymentServiceId = $factureServiceId;
                } else {
                    $paymentServiceId = null;
                }
            }

            // --------- SESSION DE CAISSE ----------
            $session = $request->attributes->get('cash_session'); // middleware si présent

            if (!$session) {
                // fallback : on cherche une session ouverte pour ce user
                $session = CashRegisterSession::where('user_id', $cashier->id)->open()->first();
            }

            if (!$session) {
                throw ValidationException::withMessages([
                    'session' => 'Ouvrez d’abord une session de caisse sur ce poste.',
                ]);
            }

            /** @var Reglement $reglement */
            $reglement = $facture->reglements()->create([
                'montant'       => $validated['montant'],
                'mode'          => $validated['mode'],
                'reference'     => $validated['reference'] ?? null,
                'devise'        => $facture->devise,

                // rattachement caisse
                'cashier_id'      => $cashier?->id,
                'cash_session_id' => $session->id,
                'workstation'     => $session->workstation,

                // ✅ rattachement service cohérent avec les règles ci-dessus
                'service_id'      => $paymentServiceId,
            ]);

            // Journal d’audit
            CashRegisterAudit::log(
                'PAYMENT_CREATED',
                $session,
                $cashier,
                [
                    'montant'        => (float) $reglement->montant,
                    'mode'           => $reglement->mode,
                    'devise'         => $reglement->devise,
                    'facture_numero' => $facture->numero ?? null,
                    'service_id'     => $paymentServiceId,
                ],
                [
                    'ip'           => $request->ip(),
                    'workstation'  => $session->workstation,
                    'facture_id'   => $facture->id,
                    'reglement_id' => $reglement->id,
                ]
            );

            // Agrégats de session
            $session->increment('payments_count');
            $session->total_amount = (float) ($session->total_amount ?? 0) + (float) $reglement->montant;
            $session->save();

            // Recalcule après création
            $facture->recalc();
            $facture->loadMissing('reglements');

            $total  = (float) $facture->montant_total;
            $reste  = (float) $facture->montant_du;
            $paye   = (float) $facture->montant_paye;

            $response = [
                'facture' => [
                    'id'            => $facture->id,
                    'numero'        => $facture->numero,
                    'statut'        => $facture->statut,
                    'devise'        => $facture->devise,
                    'total'         => $total,
                    'paye'          => $paye,
                    'reste'         => $reste,
                ],
                'reglement' => [
                    'id'         => $reglement->id,
                    'montant'    => (float) $reglement->montant,
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
