<?php

namespace App\Listeners;

use App\Events\VisiteCreated;
use App\Models\Visite;
use App\Services\ServiceRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DispatchServiceReferral implements ShouldQueue
{
    use InteractsWithQueue;

    /** Queue après COMMIT de la transaction qui a créé la visite */
    public bool $afterCommit = true;

    /** (optionnel) Confiance: nb d’essais, backoff */
    public int $tries = 3;
    public int $backoff = 10; // secondes entre essais

    private ServiceRegistry $registry;

    public function __construct(ServiceRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function handle(VisiteCreated $e): void
    {
        // Charger la visite + relations minimales
        $visit = Visite::with(['patient','service'])->find($e->visiteId);

        if (! $visit) {
            Log::warning('[Referral] visite introuvable', ['visiteId' => $e->visiteId]);
            return;
        }
        if (! $visit->service) {
            Log::warning('[Referral] service manquant sur la visite', ['visiteId' => $visit->id]);
            return;
        }

        $cfg = $visit->service->config ?? [];
        Log::debug('[Referral] start', [
            'visiteId' => $visit->id,
            'service'  => $visit->service->slug ?? null,
            'hasCfg'   => is_array($cfg),
            'model'    => is_array($cfg) ? ($cfg['model'] ?? null) : null,
            'table'    => is_array($cfg) ? ($cfg['table'] ?? null) : null,
        ]);

        $payload = [
            'visit_id'      => $visit->id,
            'patient_id'    => $visit->patient_id,
            'reason'        => $visit->plaintes_motif ?? $visit->hypothese_diagnostic ?? null,
            'statut'        => $visit->statut,
            'medecin_id'    => $visit->medecin_id,
            'medecin_nom'   => $visit->medecin_nom,
            'agent_id'      => $visit->agent_id,
            'agent_nom'     => $visit->agent_nom,
            'created_at'    => optional($visit->created_at)->toIso8601String(),
            'actor_user_id' => $e->actorUserId,
        ];

        // Appeler l’adapter configuré pour ce service
        $this->registry->for($visit->service)->handleVisit($visit, $payload);

        Log::debug('[Referral] done', ['visiteId' => $visit->id]);
    }

    /** (optionnel) Gestion des échecs: ira dans failed_jobs */
    public function failed(VisiteCreated $e, \Throwable $ex): void
    {
        Log::error('[Referral] FAILED', [
            'visiteId' => $e->visiteId,
            'error'    => $ex->getMessage(),
        ]);
    }
}
