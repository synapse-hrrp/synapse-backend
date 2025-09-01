<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VisiteStoreRequest;
use App\Http\Requests\VisiteUpdateRequest;
use App\Http\Resources\VisiteResource;
use App\Models\Visite;
use App\Models\Personnel;
use App\Models\Service;
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

        // 2) Snapshot du médecin (nom) — validé par le FormRequest (service + rôle si tu l’as ajouté)
        $medecin = Personnel::query()->whereKey($data['medecin_id'])->firstOrFail();
        $data['medecin_nom'] = $medecin->full_name
            ?? trim(($medecin->first_name ?? '').' '.($medecin->last_name ?? ''));

        // 3) Défaut heure_arrivee si non fourni (au cas où)
        $data['heure_arrivee'] = $data['heure_arrivee'] ?? now();

        // 4) Ne garder QUE les colonnes réellement présentes en DB
        $columns = Schema::getColumnListing('visites');
        $data    = array_intersect_key($data, array_flip($columns));

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
                    // silencieux : l’affectation est optionnelle
                }
            }

            return $v;
        });

        // Charger seulement les relations disponibles
        $with = ['patient','service','medecin','agent'];
        if (Schema::hasColumn('visites','tarif_id') && method_exists(Visite::class, 'tarif')) {
            $with[] = 'tarif';
        }
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

        $with = ['patient','service','medecin','agent'];
        if (Schema::hasColumn('visites','tarif_id') && method_exists(Visite::class, 'tarif')) {
            $with[] = 'tarif';
        }
        if (method_exists(Visite::class, 'affectation')) {
            $with[] = 'affectation';
        }

        $q = Visite::with($with)
            ->when($request->filled('patient_id'), fn($b)=>$b->where('patient_id',$request->patient_id))
            ->when($request->filled('service_id'), fn($b)=>$b->where('service_id',$request->service_id))
            // bonus: filtrer par slug si fourni ?service_slug=medecine
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

        $with = ['patient','service','medecin','agent'];
        if (Schema::hasColumn('visites','tarif_id') && method_exists(Visite::class, 'tarif')) {
            $with[] = 'tarif';
        }
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

        $v = Visite::findOrFail($id);
        $data = $request->validated();

        // Clôture : auto-renseigner clos_at/closed_at si statut passe à 'clos'
        if (($data['statut'] ?? null) === 'clos') {
            if (Schema::hasColumn('visites','clos_at') && !$v->clos_at && !isset($data['clos_at'])) {
                $data['clos_at'] = now();
            }
            if (Schema::hasColumn('visites','closed_at') && !$v->closed_at && !isset($data['closed_at'])) {
                $data['closed_at'] = now();
            }
        }

        // Ne mettre à jour que les colonnes existantes
        $columns = Schema::getColumnListing('visites');
        $data    = array_intersect_key($data, array_flip($columns));

        $v->fill($data)->save();

        $with = ['patient','service','medecin','agent'];
        if (Schema::hasColumn('visites','tarif_id') && method_exists(Visite::class, 'tarif')) {
            $with[] = 'tarif';
        }
        if (method_exists(Visite::class, 'affectation')) {
            $with[] = 'affectation';
        }

        return new VisiteResource($v->fresh($with));
    }
}
