<?php

// app/Http/Controllers/IncomingController.php
namespace App\Http\Controllers;

use App\Models\VisiteProxy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IncomingController extends Controller
{
    public function store(Request $r)
    {
        // 0) Métadonnées d'événement
        $event    = $r->header('X-Event', 'visite.created');
        $version  = (int) $r->header('X-Event-Version', 1);
        $accepted = config('services.core.accept_event_versions', [1]);
        abort_unless(in_array($version, $accepted, true), 422, 'Unsupported event version');

        // 1) Signature HMAC (si configurée)
        if ($secret = config('services.core.secret')) {
            $expected = hash_hmac('sha256', $r->getContent(), $secret);
            abort_unless(hash_equals($expected, (string) $r->header('X-Signature', '')), 401, 'Bad signature');
        }

        // 2) Valider le format **que tu envoies**
        //    Ici on valide le payload imbriqué que tu as partagé.
        $validated = $r->validate([
            'id'                  => 'required|string', // visite id (uuid)
            'patient'             => 'required|array',
            'patient.id'          => 'required|string',
            'patient.numero_dossier' => 'nullable|string',
            'patient.nom'         => 'nullable|string',
            'patient.prenom'      => 'nullable|string',
            'patient.age'         => 'nullable|numeric',
            'patient.sexe'        => 'nullable|string|in:M,F',
            'service'             => 'required|array',
            'service.id'          => 'nullable|integer',
            'service.code'        => 'nullable|string|max:50',
            'service.name'        => 'nullable|string|max:150',
            'medecin'             => 'nullable|array',
            'medecin.id'          => 'nullable|string',
            'medecin.nom'         => 'nullable|string|max:150',
            'agent'               => 'nullable|array',
            'agent.id'            => 'nullable|string',
            'agent.nom'           => 'nullable|string|max:150',
            'heure_arrivee'       => 'nullable|date',
            'plaintes_motif'      => 'nullable|string|max:255',
            'hypothese_diagnostic'=> 'nullable|string|max:255',
            'affectation_id'      => 'nullable',
            'statut'              => 'nullable|string|max:100',
            'clos_at'             => 'nullable|date',
            'prix'                => 'nullable|array',
            'prix.tarif_id'       => 'nullable|string',
            'prix.tarif'          => 'nullable|array',
            'prix.tarif.code'     => 'nullable|string|max:50',
            'prix.tarif.libelle'  => 'nullable|string|max:150',
            'prix.tarif.montant'  => 'nullable|numeric',
            'prix.tarif.devise'   => 'nullable|string|max:8',
            'prix.montant_prevu'  => 'nullable|numeric',
            'prix.montant_du'     => 'nullable|numeric',
            'prix.devise'         => 'nullable|string|max:8',
            'created_at'          => 'nullable|date',
            'updated_at'          => 'nullable|date',
        ]);

        // 3) Idempotency key : header si présent, sinon fallback = id
        $idemKey = (string) $r->header('X-Idempotency-Key', $validated['id'] ?? '');
        abort_if($idemKey === '', 422, 'Missing X-Idempotency-Key');

        // 4) (Optionnel) Vérifier/enregistrer l’idempotence stricte
        if (Schema::hasTable('idempotency_keys')) {
            $hash = hash('sha256', $r->getContent());
            $used = DB::table('idempotency_keys')->where('key', $idemKey)->first();

            if ($used) {
                if ($used->hash && $used->hash !== $hash) {
                    return response()->json(['message' => 'Idempotency conflict'], 409);
                }
            } else {
                DB::table('idempotency_keys')->insert([
                    'key'        => $idemKey,
                    'hash'       => $hash,
                    'used_at'    => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 5) Mapping → champs plats de VisiteProxy
        $visiteId     = $validated['id'];
        $patientId    = data_get($validated, 'patient.id');
        $serviceCode  = data_get($validated, 'service.code'); // ex: "ARU"
        $serviceSlug  = $serviceCode ? strtolower($serviceCode) : ($r->route('service') ?? null);
        $medecinId    = data_get($validated, 'medecin.id');
        $medecinNom   = data_get($validated, 'medecin.nom');
        $agentId      = data_get($validated, 'agent.id');
        $agentNom     = data_get($validated, 'agent.nom');

        // Prix
        $tarifId        = data_get($validated, 'prix.tarif_id'); // string "1" dans ton exemple
        $montantPrevu   = data_get($validated, 'prix.montant_prevu', data_get($validated, 'prix.tarif.montant', 0));
        $montantDu      = data_get($validated, 'prix.montant_du', $montantPrevu);
        $devise         = data_get($validated, 'prix.devise', data_get($validated, 'prix.tarif.devise', 'XAF'));

        // 6) Upsert idempotent
        $payload = [
            'visite_id'         => (string) $visiteId,
            'service_slug'      => $serviceSlug,
            'patient_id'        => (string) $patientId,
            'medecin_id'        => $medecinId ? (string) $medecinId : null,
            'medecin_nom'       => $medecinNom ?: null,
            'agent_id'          => $agentId ? (string) $agentId : null,
            'agent_nom'         => $agentNom ?: null,
            'heure_arrivee'     => $validated['heure_arrivee'] ?? null,
            'plaintes_motif'    => $validated['plaintes_motif'] ?? null,
            // on mappe hypothese_diagnostic -> hypothese
            'hypothese'         => $validated['hypothese_diagnostic'] ?? null,
            'statut'            => $validated['statut'] ?? null,
            'tarif_id'          => $tarifId ? (int) $tarifId : null,
            'montant_prevu'     => (float) $montantPrevu,
            'montant_du'        => (float) $montantDu,
            'devise'            => $devise ?: 'XAF',
            'est_soldee'        => (bool) (isset($validated['prix.montant_du']) ? $montantDu <= 0 : false),

            'source_created_at' => $validated['created_at'] ?? null,
            'source_updated_at' => $validated['updated_at'] ?? null,

            'raw'               => json_decode($r->getContent(), true),
        ];

        $record = VisiteProxy::updateOrCreate(
            ['visite_id' => $payload['visite_id']],
            $payload
        );

        return response()->json([
            'ok'        => true,
            'id'        => $record->id,
            'visite_id' => $record->visite_id,
            'service'   => $record->service_slug,
        ], 200);
    }
}
