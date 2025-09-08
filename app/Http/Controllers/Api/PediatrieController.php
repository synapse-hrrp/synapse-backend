<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PediatrieStoreRequest;
use App\Http\Requests\PediatrieUpdateRequest;
use App\Http\Resources\PediatrieResource;
use App\Models\Pediatrie;
use App\Models\Visite;
use Illuminate\Http\Request;

class PediatrieController extends Controller
{
    /**
     * GET /api/v1/pediatrie
     * Filtres: ?patient_id=…&statut=…&q=…&sort=-date_acte&limit=20
     * Options: ?only_trashed=1 (corbeille) | ?with_trashed=1 (inclure corbeille)
     */
    public function index(Request $request)
    {
        $patientId   = (string) $request->query('patient_id', '');
        $statut      = (string) $request->query('statut', '');
        $q           = (string) $request->query('q', '');
        $sortRaw     = (string) $request->query('sort', '-date_acte');
        $onlyTrashed = $request->boolean('only_trashed', false);
        $withTrashed = $request->boolean('with_trashed', false);

        $field = ltrim($sortRaw, '-');
        $dir   = str_starts_with($sortRaw, '-') ? 'desc' : 'asc';
        if (!in_array($field, ['date_acte','created_at','statut'], true)) {
            $field = 'date_acte';
        }

        $query = Pediatrie::query()
            ->when($onlyTrashed, fn($q) => $q->onlyTrashed())
            ->when(!$onlyTrashed && $withTrashed, fn($q) => $q->withTrashed())
            ->with(['patient','visite','soignant:id,name,email'])
            ->when($patientId !== '', fn($q2) => $q2->where('patient_id', $patientId))
            ->when($statut !== '', fn($q2) => $q2->where('statut', $statut))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($b) use ($q) {
                    $b->where('motif','like',"%{$q}%")
                      ->orWhere('diagnostic','like',"%{$q}%")
                      ->orWhere('examen_clinique','like',"%{$q}%")
                      ->orWhere('traitements','like',"%{$q}%")
                      ->orWhere('observation','like',"%{$q}%");
                });
            })
            ->orderBy($field, $dir);

        $perPage = min(max((int)$request->query('limit', 20), 1), 200);
        $items   = $query->paginate($perPage);

        return PediatrieResource::collection($items)->additional([
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
            'only_trashed' => $onlyTrashed,
            'with_trashed' => $withTrashed,
        ]);
    }

    /**
     * POST /api/v1/pediatrie
     * soignant_id = user connecté ; visite_id = dernière visite si absente
     */
    public function store(PediatrieStoreRequest $request)
    {
        $data = $request->validated();

        if ($request->user()) {
            $data['soignant_id'] = $request->user()->id;
        }

        if (empty($data['visite_id'])) {
            $data['visite_id'] = Visite::where('patient_id', $data['patient_id'])
                ->orderByDesc('heure_arrivee')
                ->value('id');
        }

        $data['date_acte'] = $data['date_acte'] ?? now();
        $data['statut']    = $data['statut'] ?? 'en_cours';

        $item = Pediatrie::create($data);

        return (new PediatrieResource(
            $item->load(['patient','visite','soignant:id,name,email'])
        ))->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/pediatrie/{pediatrie}
     */
    public function show(Pediatrie $pediatrie)
    {
        $pediatrie->load(['patient','visite','soignant:id,name,email']);
        return new PediatrieResource($pediatrie);
    }

    /**
     * PATCH/PUT /api/v1/pediatrie/{pediatrie}
     */
    public function update(PediatrieUpdateRequest $request, Pediatrie $pediatrie)
    {
        $data = $request->validated();

        if ((!array_key_exists('visite_id',$data) || empty($data['visite_id'])) && ($data['patient_id'] ?? $pediatrie->patient_id)) {
            $pid = $data['patient_id'] ?? $pediatrie->patient_id;
            $deduced = Visite::where('patient_id', $pid)->orderByDesc('heure_arrivee')->value('id');
            if ($deduced) $data['visite_id'] = $deduced;
        }

        $pediatrie->fill($data)->save();
        $pediatrie->load(['patient','visite','soignant:id,name,email']);

        return new PediatrieResource($pediatrie);
    }

    /**
     * DELETE /api/v1/pediatrie/{pediatrie}
     * Envoie à la corbeille (soft delete) et renvoie un message.
     */
    public function destroy(Pediatrie $pediatrie)
    {
        $pediatrie->delete(); // corbeille
        return response()->json([
            'message' => 'Acte pédiatrie envoyé à la corbeille.',
            'deleted' => true,
            'id'      => $pediatrie->id,
        ], 200);
    }

    /**
     * GET /api/v1/pediatrie-corbeille
     * Liste uniquement les éléments en corbeille (soft-deleted).
     */
    public function trash(Request $request)
    {
        $perPage = min(max((int)$request->query('limit', 20), 1), 200);

        $items = Pediatrie::onlyTrashed()
            ->with(['patient','visite','soignant:id,name,email'])
            ->orderByDesc('deleted_at')
            ->paginate($perPage);

        return PediatrieResource::collection($items)->additional([
            'trash' => true,
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }

    /**
     * POST /api/v1/pediatrie/{id}/restore
     * Restaure un enregistrement soft-deleted.
     */
    public function restore(string $id)
    {
        $item = Pediatrie::onlyTrashed()->findOrFail($id);
        $item->restore();

        $item->load(['patient','visite','soignant:id,name,email']);

        return (new PediatrieResource($item))
            ->additional(['restored' => true]);
    }

    /**
     * DELETE /api/v1/pediatrie/{id}/force
     * (Optionnel) suppression définitive depuis la corbeille.
     */
    public function forceDestroy(string $id)
    {
        $item = Pediatrie::onlyTrashed()->findOrFail($id);
        $item->forceDelete();

        return response()->json([
            'message' => 'Acte pédiatrie supprimé définitivement.',
            'force_deleted' => true,
            'id' => $id,
        ]);
    }
}
