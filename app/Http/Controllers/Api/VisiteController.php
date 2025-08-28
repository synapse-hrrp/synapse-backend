<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VisiteStoreRequest;
use App\Http\Requests\VisiteUpdateRequest;
use App\Http\Resources\VisiteResource;
use App\Models\Visite;
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
        $data['agent_id'] = $request->user()->id;
        $data['agent_nom'] = $request->user()->name ?? $request->user()->email;

        // ── Pricing auto (si colonnes + modèle Tarif existent) ─────────────────
        
        // ── Création + (option) affectation vers le service ───────────────────
        $visite = DB::transaction(function () use ($data) {
            // créer la visite
            $v = Visite::create($data);

            // Si tu as le module Affectation et que tu veux alimenter la file
        
            return $v;
        });

        return (new VisiteResource($visite->load(['patient','service','medecin','agent','tarif'])))
            ->response()->setStatusCode(201);
    }

    // GET /api/v1/visites
    public function index(Request $request)
    {
        if (! $request->user()->tokenCan('*') && ! $request->user()->tokenCan('visites.read')) {
            return response()->json(['message'=>'Forbidden: visites.read requis'], 403);
        }

        $q = Visite::with(['patient','service','medecin','agent'])
            ->when($request->filled('patient_id'), fn($b)=>$b->where('patient_id',$request->patient_id))
            ->when($request->filled('service_code'), fn($b)=>$b->where('service_code',$request->service_code))
            ->when($request->filled('statut'), fn($b)=>$b->where('statut',$request->statut))
            ->orderByDesc('created_at');

        $perPage = min(max((int)$request->get('limit', 20), 1), 200);
        $items = $q->paginate($perPage);

        // si vide, renvoyer la structure vide propre
        if ($items->count() === 0) {
            return response()->json([
                'data' => [],
                'page' => 1,
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

        $v = Visite::with(['patient','service','medecin','agent','tarif'])->findOrFail($id);
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

        if (($data['statut'] ?? null) === 'clos' && !$v->clos_at) {
            $v->clos_at = now();
        }

        $v->fill($data)->save();

        return new VisiteResource($v->fresh(['patient','service','medecin','agent','tarif']));
    }
}
