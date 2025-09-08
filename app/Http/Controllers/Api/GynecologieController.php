<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GynecologieStoreRequest;
use App\Http\Requests\GynecologieUpdateRequest;
use App\Http\Resources\GynecologieResource;
use App\Models\Gynecologie;
use App\Models\Visite;
use Illuminate\Http\Request;

class GynecologieController extends Controller
{
    /**
     * GET /api/v1/gynecologie
     * Filtres: ?patient_id=…&statut=…&q=…&sort=-date_acte&limit=20
     * Corbeille: ?only_trashed=1 (seulement corbeille) | ?with_trashed=1 (inclure corbeille)
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

        $query = Gynecologie::query()
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

        return GynecologieResource::collection($items)->additional([
            'page'         => $items->currentPage(),
            'limit'        => $items->perPage(),
            'total'        => $items->total(),
            'only_trashed' => $onlyTrashed,
            'with_trashed' => $withTrashed,
        ]);
    }

    /**
     * POST /api/v1/gynecologie
     */
    public function store(GynecologieStoreRequest $request)
    {
        $data = $request->validated();

        // soignant = user connecté
        if ($request->user()) {
            $data['soignant_id'] = $request->user()->id;
        }

        // déduire la visite si absente
        if (empty($data['visite_id'])) {
            $data['visite_id'] = Visite::where('patient_id', $data['patient_id'])
                ->orderByDesc('heure_arrivee')
                ->value('id');
        }

        $data['date_acte'] = $data['date_acte'] ?? now();
        $data['statut']    = $data['statut'] ?? 'en_cours';

        $item = Gynecologie::create($data);

        return (new GynecologieResource(
            $item->load(['patient','visite','soignant:id,name,email'])
        ))->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/gynecologie/{gynecologie}
     */
    public function show(Gynecologie $gynecologie)
    {
        $gynecologie->load(['patient','visite','soignant:id,name,email']);
        return new GynecologieResource($gynecologie);
    }

    /**
     * PATCH/PUT /api/v1/gynecologie/{gynecologie}
     */
    public function update(GynecologieUpdateRequest $request, Gynecologie $gynecologie)
    {
        $data = $request->validated();

        // si visite absente, tente de déduire depuis le patient
        if ((!array_key_exists('visite_id',$data) || empty($data['visite_id'])) && ($data['patient_id'] ?? $gynecologie->patient_id)) {
            $pid = $data['patient_id'] ?? $gynecologie->patient_id;
            $deduced = Visite::where('patient_id', $pid)->orderByDesc('heure_arrivee')->value('id');
            if ($deduced) $data['visite_id'] = $deduced;
        }

        $gynecologie->fill($data)->save();
        $gynecologie->load(['patient','visite','soignant:id,name,email']);

        return new GynecologieResource($gynecologie);
    }

    /**
     * DELETE /api/v1/gynecologie/{gynecologie}
     * Envoie à la corbeille (soft delete) et renvoie un message.
     */
    public function destroy(Gynecologie $gynecologie)
    {
        $gynecologie->delete(); // corbeille
        return response()->json([
            'message' => 'Acte gynécologie envoyé à la corbeille.',
            'deleted' => true,
            'id'      => $gynecologie->id,
        ], 200);
    }

    /**
     * GET /api/v1/gynecologie-corbeille
     * Liste uniquement les éléments en corbeille.
     */
    public function trash(Request $request)
    {
        $perPage = min(max((int)$request->query('limit', 20), 1), 200);

        $items = Gynecologie::onlyTrashed()
            ->with(['patient','visite','soignant:id,name,email'])
            ->orderByDesc('deleted_at')
            ->paginate($perPage);

        return GynecologieResource::collection($items)->additional([
            'trash' => true,
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }

    /**
     * POST /api/v1/gynecologie/{id}/restore
     * Restaure un enregistrement soft-deleted.
     */
    public function restore(string $id)
    {
        $item = Gynecologie::onlyTrashed()->findOrFail($id);
        $item->restore();

        $item->load(['patient','visite','soignant:id,name,email']);

        return (new GynecologieResource($item))
            ->additional(['restored' => true]);
    }

    /**
     * DELETE /api/v1/gynecologie/{id}/force
     * Suppression définitive depuis la corbeille.
     */
    public function forceDestroy(string $id)
    {
        $item = Gynecologie::onlyTrashed()->findOrFail($id);
        $item->forceDelete();

        return response()->json([
            'message' => 'Acte gynécologie supprimé définitivement.',
            'force_deleted' => true,
            'id' => $id,
        ]);
    }
}
