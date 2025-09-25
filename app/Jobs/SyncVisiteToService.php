<?php
// app/Jobs/SyncVisiteToService.php

namespace App\Jobs;

use App\Models\Visite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SyncVisiteToService implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;      // retries
    public int $backoff = 60;   // 60s
    public int $timeout = 15;   // 15s HTTP timeout

    public function __construct(public string $visiteId) {}

    public function handle(): void
    {
        $visite = Visite::with(['patient','service','medecin','agent','tarif'])->findOrFail($this->visiteId);
        $service = $visite->service;

        // Si pas de config webhook, on s’arrête proprement
        if (! $service || ! $service->webhook_enabled || ! $service->webhook_url) {
            return;
        }

        // Construire le payload conforme à IncomingController
        $payload = [
            'visite_id'      => (string) $visite->id,
            'service_slug'   => $service->slug ?? null,
            'patient_id'     => (string) $visite->patient_id,
            'medecin_id'     => $visite->medecin_id ? (string) $visite->medecin_id : null,
            'medecin_nom'    => $visite->medecin_nom ?: null,
            'agent_id'       => $visite->agent_id ? (string) $visite->agent_id : null,
            'agent_nom'      => $visite->agent_nom ?: null,
            'heure_arrivee'  => optional($visite->heure_arrivee)->toIso8601String(),
            'plaintes_motif' => $visite->plaintes_motif,
            'hypothese'      => $visite->hypothese_diagnostic, // mapping -> hypothese
            'statut'         => $visite->statut,
            'tarif_id'       => $visite->tarif_id,
            'montant_prevu'  => (float) $visite->montant_prevu,
            'montant_du'     => (float) $visite->montant_du,
            'devise'         => $visite->devise,
            'est_soldee'     => (bool) $visite->est_soldee,
            'created_at'     => optional($visite->created_at)->toIso8601String(),
            'updated_at'     => optional($visite->updated_at)->toIso8601String(),
        ];

        // En-têtes
        $headers = [
            'X-Idempotency-Key' => (string) $visite->id,
            'X-Event'           => $service->webhook_event ?: 'visite.created',
            'X-Event-Version'   => 1,
        ];

        // Auth inter-services (Bearer)
        $req = Http::acceptJson()->timeout($this->timeout)->retry(3, 200);
        if ($service->webhook_token) {
            $req = $req->withToken($service->webhook_token);
        }

        // Signature HMAC optionnelle
        if ($service->webhook_secret) {
            $headers['X-Signature'] = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), $service->webhook_secret);
        }

        $req = $req->withHeaders($headers);

        // URL + méthode (POST par défaut). On finit sur /incoming si tu as choisi ce pattern.
        $base = rtrim($service->webhook_url, '/');
        $url  = $base . '/incoming';
        $method = strtoupper($service->webhook_method ?: 'POST');

        $resp = match ($method) {
            'PUT'   => $req->put($url,   $payload),
            'PATCH' => $req->patch($url, $payload),
            default => $req->post($url,  $payload),
        };

        // Tolérer 200/201/204 et aussi 409/422 (idempotence/validation douce)
        if (! $resp->successful() && ! in_array($resp->status(), [200,201,204,409,422], true)) {
            $this->release($this->backoff);
            throw new \RuntimeException("Sync to service {$service->slug} failed: {$resp->status()}");
        }
    }
}
