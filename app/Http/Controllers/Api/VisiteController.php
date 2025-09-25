<?php

namespace App\Http\Controllers\Api;

use App\Events\VisiteCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\VisiteStoreRequest;
use App\Http\Requests\VisiteUpdateRequest;
use App\Http\Resources\VisiteResource;
use App\Models\Visite;
use App\Models\Personnel;
use App\Models\Service;
use App\Models\Tarif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VisiteController extends Controller
{
    // POST /api/v1/visites
    public function store(VisiteStoreRequest $request)
    {
        if (! $request->user()->tokenCan('*') && ! $request->user()->tokenCan('visites.write')) {
            return response()->json(['message'=>'Forbidden: visites.write requis'], 403);
        }

        $data = $request->validated();

        // 🔁 Convertir service_slug -> service_id (AVANT toute vérification)
        $selectedServiceSlug = null;
        if (!empty($data['service_slug'])) {
            $resolved = Service::query()
                ->select(['id','slug','is_active'])
                ->where('slug', $data['service_slug'])
                ->first();

            if (! $resolved) {
                return response()->json(['message' => 'Service introuvable'], 422);
            }

            // Si les deux sont fournis, ils doivent correspondre
            if (!empty($data['service_id']) && (string)$data['service_id'] !== (string)$resolved->id) {
                return response()->json(['message' => 'service_id et service_slug ne correspondent pas'], 422);
            }

            $data['service_id'] = (int) $resolved->id; // (si UUID chez toi, enlève le cast)
            $selectedServiceSlug = $resolved->slug;
            unset($data['service_slug']);
        }

        // 0) Service doit exister & être actif (optionnel mais utile)
        if (! empty($data['service_id'])) {
            $service = Service::query()->select(['slug','is_active'])->whereKey($data['service_id'])->first();
            if (! $service) {
                return response()->json(['message' => 'Service introuvable'], 422);
            }
            if ($service->is_active === 0 || $service->is_active === false) {
                return response()->json(['message' => 'Service inactif'], 422);
            }
            // si pas déjà résolu via slug plus haut
            $selectedServiceSlug = $selectedServiceSlug ?: $service->slug;
        }

        // 1) Récupérer le Personnel de l'utilisateur connecté (agent)
        $agentPersonnel = $request->user()?->personnel;
        if (! $agentPersonnel) {
            return response()->json([
                'message' => 'Aucun personnel rattaché à l’utilisateur connecté.'
            ], 422);
        }
        $data['agent_id']  = $agentPersonnel->id;
        $data['agent_nom'] = $agentPersonnel->full_name
            ?? trim(($agentPersonnel->first_name ?? '').' '.($agentPersonnel->last_name ?? ''));

        // 2) Snapshot du médecin (optionnel)
        if (!empty($data['medecin_id'])) {
            $medecin = Personnel::query()->whereKey($data['medecin_id'])->firstOrFail();
            $data['medecin_nom'] = $medecin->full_name
                ?? trim(($medecin->first_name ?? '').' '.($medecin->last_name ?? ''));
        } else {
            $data['medecin_nom'] = $data['medecin_nom'] ?? null;
        }

        // 3) heure_arrivee par défaut
        $data['heure_arrivee'] = $data['heure_arrivee'] ?? now();

        // 4) Statut : garder uniquement ceux gérés par le modèle (sinon on laisse le default du model)
        if (isset($data['statut'])) {
            $allowed = ['EN_ATTENTE','A_ENCAISSER','PAYEE','CLOTUREE'];
            if (! in_array($data['statut'], $allowed, true)) {
                unset($data['statut']);
            }
        }

        // 5) ------- TARIF : résoudre tarif_id / tarif_code (cohérence via service_slug) -------
        // Laisse le modèle fixer la devise via le tarif (sinon mets 'XAF' si tu veux forcer une valeur)
        if (array_key_exists('devise', $data) && empty($data['devise'])) {
            unset($data['devise']); // le modèle Visite remplira depuis $v->tarif->devise (défaut XAF)
        }

        $tarif = null;

        if (!empty($data['tarif_id'])) {
            $tarif = Tarif::query()
                ->whereKey($data['tarif_id'])
                ->where('is_active', true)
                ->first();
        }

        if (! $tarif && $request->filled('tarif_code')) {
            $tarif = Tarif::query()
                ->where('code', strtoupper(trim($request->input('tarif_code'))))
                ->where('is_active', true)
                ->first();
            if ($tarif) {
                $data['tarif_id'] = $tarif->id;
            }
        }

        // ✅ Cohérence service/tarif via service_slug (nouveau schéma)
        if ($tarif && $tarif->service_slug && $selectedServiceSlug && $tarif->service_slug !== $selectedServiceSlug) {
            return response()->json([
                'message' => "Le tarif choisi n'appartient pas au service sélectionné."
            ], 422);
        }

        // ⚠️ Ne pas calculer ici montant_prevu/devise/montant_du : le modèle Visite le fera dans booted()

        // 6) Ne garder QUE les colonnes réellement présentes en DB
        $columns = Schema::getColumnListing('visites');
        $data    = array_intersect_key($data, array_flip($columns));

        $userId = optional($request->user())->id;

        $visite = DB::transaction(function () use ($data, $request) {
            $v = Visite::create($data);

            // Option : créer/mettre à jour l’affectation patient→service si demandé
            if ($request->boolean('create_affectation') && Schema::hasTable('patient_service')) {
                try {
                    $v->patient?->services()->syncWithoutDetaching([
                        $v->service_id => [
                            'started_at'  => now(),
                            'is_primary'  => false,
                            'assigned_by' => $request->user()->id ?? null,
                        ],
                    ]);
                } catch (\Throwable $e) {
                    // silencieux
                }
            }

            return $v;
        });

        // ✅ Déclencher l’event APRÈS COMMIT + avec l’ID de l’acteur
        DB::afterCommit(function () use ($visite, $userId) {
            event(new VisiteCreated($visite->id, $userId));
        });

        // Charger les relations
        $with = ['patient','service','medecin','agent','tarif'];
        if (method_exists(Visite::class, 'affectation')) {
            $with[] = 'affectation';
        }

        return (new VisiteResource($visite->load($with)))->response()->setStatusCode(201);
    }

    // GET /api/v1/visites
    public function index(Request $request)
    {
        if (! $request->user()->tokenCan('*') && ! $request->user()->tokenCan('visites.read')) {
            return response()->json(['message'=>'Forbidden: visites.read requis'], 403);
        }

        $with = ['patient','service','medecin','agent','tarif'];
        if (method_exists(Visite::class, 'affectation')) {
            $with[] = 'affectation';
        }

        $q = Visite::with($with)
            ->when($request->filled('patient_id'), fn($b)=>$b->where('patient_id',$request->patient_id))
            ->when($request->filled('service_id'), fn($b)=>$b->where('service_id',$request->service_id))
            ->when($request->filled('service_slug'), function ($b) use ($request) {
                $serviceId = Service::where('slug', $request->service_slug)->value('id');
                if ($serviceId) $b->where('service_id', $serviceId);
            })
            ->when($request->filled('statut'), fn($b)=>$b->where('statut',$request->statut))
            ->orderByDesc('created_at');

        $perPage = min(max((int)$request->get('limit', 20), 1), 200);
        $items = $q->paginate($perPage);

        if ($items->count() === 0) {
            return response()->json([
                'data'  => [],
                'page'  => 1,
                'limit' => $perPage,
                'total' => 0,
            ], 200);
        }

        return VisiteResource::collection($items)->additional([
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }

    // GET /api/v1/visites/{id}
    public function show(Request $request, string $id)
    {
        if (! $request->user()->tokenCan('*') && ! $request->user()->tokenCan('visites.read')) {
            return response()->json(['message'=>'Forbidden: visites.read requis'], 403);
        }

        $with = ['patient','service','medecin','agent','tarif'];
        if (method_exists(Visite::class, 'affectation')) {
            $with[] = 'affectation';
        }

        $v = Visite::with($with)->findOrFail($id);
        return new VisiteResource($v);
    }

    // PATCH /api/v1/visites/{id}
    public function update(VisiteUpdateRequest $request, string $id)
    {
        if (! $request->user()->tokenCan('*') && ! $request->user()->tokenCan('visites.write')) {
            return response()->json(['message'=>'Forbidden: visites.write requis'], 403);
        }

        $v    = Visite::findOrFail($id);
        $data = $request->validated();

        // 🔁 Supporter service_slug à l’update (convertir en service_id)
        if (!empty($data['service_slug'])) {
            $resolvedId = Service::where('slug', $data['service_slug'])->value('id');
            if (! $resolvedId) {
                return response()->json(['message' => 'Service introuvable'], 422);
            }
            $data['service_id'] = (int) $resolvedId; // (si UUID, enlève le cast)
            unset($data['service_slug']);
        }

        // Clôture : si statut devient 'CLOTUREE', auto-remplir clos_at/closed_at si présent dans le schéma
        if (($data['statut'] ?? null) === 'CLOTUREE') {
            if (Schema::hasColumn('visites','clos_at') && ! $v->clos_at && ! isset($data['clos_at'])) {
                $data['clos_at'] = now();
            }
            if (Schema::hasColumn('visites','closed_at') && ! $v->closed_at && ! isset($data['closed_at'])) {
                $data['closed_at'] = now();
            }
        }

        // Statuts autorisés seulement
        if (isset($data['statut'])) {
            $allowed = ['EN_ATTENTE','A_ENCAISSER','PAYEE','CLOTUREE'];
            if (! in_array($data['statut'], $allowed, true)) {
                unset($data['statut']);
            }
        }

        // (Optionnel) si on modifie tarif_id, vérifier qu'il correspond au service (via slug)
        if (!empty($data['tarif_id'])) {
            $tarif = Tarif::query()->whereKey($data['tarif_id'])->first();
            if ($tarif && $tarif->service_slug) {
                // service cible après update (sinon service actuel)
                $serviceIdAfter = $data['service_id'] ?? $v->service_id;
                $serviceSlug    = Service::whereKey($serviceIdAfter)->value('slug');
                if ($serviceSlug && $tarif->service_slug !== $serviceSlug) {
                    return response()->json([
                        'message' => "Le tarif choisi n'appartient pas au service sélectionné."
                    ], 422);
                }
            }
        }
        // 2) Snapshot du médecin (obligatoire)
        if (!empty($data['medecin_id'])) {
            $medecin = Personnel::query()->whereKey($data['medecin_id'])->firstOrFail();
            $data['medecin_nom'] = $medecin->full_name
                ?? trim(($medecin->first_name ?? '').' '.($medecin->last_name ?? ''));
        } else {
            return response()->json([
                'message' => "Impossible de créer la visite : un médecin est requis."
            ], 422);
        }


        // Ne mettre à jour que les colonnes existantes
        $columns = Schema::getColumnListing('visites');
        $data    = array_intersect_key($data, array_flip($columns));

        $v->fill($data)->save();

        $with = ['patient','service','medecin','agent','tarif'];
        if (method_exists(Visite::class, 'affectation')) {
            $with[] = 'affectation';
        }

        return new VisiteResource($v->fresh($with));
    }
}
