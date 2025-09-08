<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SanitaireStoreRequest;
use App\Http\Requests\SanitaireUpdateRequest;
use App\Http\Resources\SanitaireResource;
use App\Models\Sanitaire;
use App\Models\Visite;
use Illuminate\Http\Request;

class SanitaireController extends Controller
{
    // GET /api/v1/sanitaire
    // Filtres: ?patient_id=…&statut=…&type_action=…&q=…&sort=-date_acte&limit=20
    // Corbeille: ?only_trashed=1 | ?with_trashed=1
    public function index(Request $request)
    {
        $patientId   = (string) $request->query('patient_id', '');
        $statut      = (string) $request->query('statut', '');
        $typeAction  = (string) $request->query('type_action', '');
        $q           = (string) $request->query('q', '');
        $sortRaw     = (string) $request->query('sort', '-date_acte');
        $onlyTrashed = $request->boolean('only_trashed', false);
        $withTrashed = $request->boolean('with_trashed', false);

        $field = ltrim($sortRaw, '-');
        $dir   = str_starts_with($sortRaw, '-') ? 'desc' : 'asc';
        if (!in_array($field, ['date_acte','created_at','statut','type_action'], true)) {
            $field = 'date_acte';
        }

        $query = Sanitaire::query()
            ->when($onlyTrashed, fn($q) => $q->onlyTrashed())
            ->when(!$onlyTrashed && $withTrashed, fn($q) => $q->withTrashed())
            ->with(['patient','visite','soignant:id,name,email'])
            ->when($patientId !== '', fn($q2) => $q2->where('patient_id', $patientId))
            ->when($statut !== '', fn($q2) => $q2->where('statut', $statut))
            ->when($typeAction !== '', fn($q2) => $q2->where('type_action', $typeAction))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($b) use ($q) {
                    $b->where('zone','like',"%{$q}%")
                      ->orWhere('sous_zone','like',"%{$q}%")
                      ->orWhere('produits_utilises','like',"%{$q}%")
                      ->orWhere('observation','like',"%{$q}%");
                });
            })
            ->orderBy($field, $dir);

        $perPage = min(max((int)$request->query('limit', 20), 1), 200);
        $items   = $query->paginate($perPage);

        return SanitaireResource::collection($items)->additional([
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
            'only_trashed' => $onlyTrashed,
            'with_trashed' => $withTrashed,
        ]);
    }

    // POST /api/v1/sanitaire
    public function store(SanitaireStoreRequest $request)
    {
        $data = $request->validated();

        // soignant = user connecté
        if ($request->user()) {
            $data['soignant_id'] = $request->user()->id;
        }

        // si visite absente mais patient fourni, déduire la dernière visite
        if (empty($data['visite_id']) && !empty($data['patient_id'])) {
            $data['visite_id'] = Visite::where('patient_id', $data['patient_id'])
                ->orderByDesc('heure_arrivee')
                ->value('id');
        }

        $data['date_acte'] = $data['date_acte'] ?? now();
        $data['statut']    = $data['statut'] ?? 'planifie';

        $item = Sanitaire::create($data);

        return (new SanitaireResource(
            $item->load(['patient','visite','soignant:id,name,email'])
        ))->response()->setStatusCode(201);
    }

    // GET /api/v1/sanitaire/{sanitaire}
    public function show(Sanitaire $sanitaire)
    {
        $sanitaire->load(['patient','visite','soignant:id,name,email']);
        return new SanitaireResource($sanitaire);
    }

    // PATCH/PUT /api/v1/sanitaire/{sanitaire}
    public function update(SanitaireUpdateRequest $request, Sanitaire $sanitaire)
    {
        $data = $request->validated();

        if ((!array_key_exists('visite_id',$data) || empty($data['visite_id'])) && ($data['patient_id'] ?? $sanitaire->patient_id)) {
            $pid = $data['patient_id'] ?? $sanitaire->patient_id;
            $deduced = Visite::where('patient_id', $pid)->orderByDesc('heure_arrivee')->value('id');
            if ($deduced) $data['visite_id'] = $deduced;
        }

        $sanitaire->fill($data)->save();
        $sanitaire->load(['patient','visite','soignant:id,name,email']);

        return new SanitaireResource($sanitaire);
    }

    // DELETE /api/v1/sanitaire/{sanitaire} -> corbeille (soft delete)
    public function destroy(Sanitaire $sanitaire)
    {
        $sanitaire->delete();

        return response()->json([
            'message' => 'Acte sanitaire envoyé à la corbeille.',
            'deleted' => true,
            'id'      => $sanitaire->id,
        ], 200);
    }

    // GET /api/v1/sanitaire-corbeille -> liste corbeille
    public function trash(Request $request)
    {
        $perPage = min(max((int)$request->query('limit', 20), 1), 200);

        $items = Sanitaire::onlyTrashed()
            ->with(['patient','visite','soignant:id,name,email'])
            ->orderByDesc('deleted_at')
            ->paginate($perPage);

        return SanitaireResource::collection($items)->additional([
            'trash' => true,
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }

    // POST /api/v1/sanitaire/{id}/restore -> restaure depuis corbeille
    public function restore(string $id)
    {
        $item = Sanitaire::onlyTrashed()->findOrFail($id);
        $item->restore();

        $item->load(['patient','visite','soignant:id,name,email']);

        return (new SanitaireResource($item))
            ->additional(['restored' => true]);
    }

    // DELETE /api/v1/sanitaire/{id}/force -> suppression définitive
    public function forceDestroy(string $id)
    {
        $item = Sanitaire::onlyTrashed()->findOrFail($id);
        $item->forceDelete();

        return response()->json([
            'message' => 'Acte sanitaire supprimé définitivement.',
            'force_deleted' => true,
            'id' => $id,
        ]);
    }
}
