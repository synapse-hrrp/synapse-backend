<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GestionMaladeStoreRequest;
use App\Http\Requests\GestionMaladeUpdateRequest;
use App\Http\Resources\GestionMaladeResource;
use App\Models\GestionMalade;
use App\Models\Visite;
use Illuminate\Http\Request;

class GestionMaladeController extends Controller
{
    // GET /api/v1/gestion-malade
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

        $query = GestionMalade::query()
            ->when($onlyTrashed, fn($q) => $q->onlyTrashed())
            ->when(!$onlyTrashed && $withTrashed, fn($q) => $q->withTrashed())
            ->with(['patient','visite','soignant:id,name,email'])
            ->when($patientId !== '', fn($q2) => $q2->where('patient_id', $patientId))
            ->when($statut !== '', fn($q2) => $q2->where('statut', $statut))
            ->when($typeAction !== '', fn($q2) => $q2->where('type_action', $typeAction))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($b) use ($q) {
                    $b->where('motif','like',"%{$q}%")
                      ->orWhere('diagnostic','like',"%{$q}%")
                      ->orWhere('examen_clinique','like',"%{$q}%")
                      ->orWhere('traitements','like',"%{$q}%")
                      ->orWhere('observation','like',"%{$q}%")
                      ->orWhere('service_source','like',"%{$q}%")
                      ->orWhere('service_destination','like',"%{$q}%")
                      ->orWhere('pavillon','like',"%{$q}%")
                      ->orWhere('chambre','like',"%{$q}%")
                      ->orWhere('lit','like',"%{$q}%");
                });
            })
            ->orderBy($field, $dir);

        $perPage = min(max((int)$request->query('limit', 20), 1), 200);
        $items   = $query->paginate($perPage);

        return GestionMaladeResource::collection($items)->additional([
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
            'only_trashed' => $onlyTrashed,
            'with_trashed' => $withTrashed,
        ]);
    }

    // POST /api/v1/gestion-malade
    public function store(GestionMaladeStoreRequest $request)
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

        $item = GestionMalade::create($data);

        return (new GestionMaladeResource(
            $item->load(['patient','visite','soignant:id,name,email'])
        ))->response()->setStatusCode(201);
    }

    // GET /api/v1/gestion-malade/{gestion_malade}
    public function show(GestionMalade $gestion_malade)
    {
        $gestion_malade->load(['patient','visite','soignant:id,name,email']);
        return new GestionMaladeResource($gestion_malade);
    }

    // PATCH/PUT /api/v1/gestion-malade/{gestion_malade}
    public function update(GestionMaladeUpdateRequest $request, GestionMalade $gestion_malade)
    {
        $data = $request->validated();

        if ((!array_key_exists('visite_id',$data) || empty($data['visite_id'])) && ($data['patient_id'] ?? $gestion_malade->patient_id)) {
            $pid = $data['patient_id'] ?? $gestion_malade->patient_id;
            $deduced = Visite::where('patient_id', $pid)->orderByDesc('heure_arrivee')->value('id');
            if ($deduced) $data['visite_id'] = $deduced;
        }

        $gestion_malade->fill($data)->save();
        $gestion_malade->load(['patient','visite','soignant:id,name,email']);

        return new GestionMaladeResource($gestion_malade);
    }

    // DELETE /api/v1/gestion-malade/{gestion_malade} -> corbeille (soft delete)
    public function destroy(GestionMalade $gestion_malade)
    {
        $gestion_malade->delete();

        return response()->json([
            'message' => 'Dossier envoyé à la corbeille.',
            'deleted' => true,
            'id'      => $gestion_malade->id,
        ], 200);
    }

    // GET /api/v1/gestion-malade-corbeille -> liste corbeille
    public function trash(Request $request)
    {
        $perPage = min(max((int)$request->query('limit', 20), 1), 200);

        $items = GestionMalade::onlyTrashed()
            ->with(['patient','visite','soignant:id,name,email'])
            ->orderByDesc('deleted_at')
            ->paginate($perPage);

        return GestionMaladeResource::collection($items)->additional([
            'trash' => true,
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }

    // POST /api/v1/gestion-malade/{id}/restore -> restaure depuis corbeille
    public function restore(string $id)
    {
        $item = GestionMalade::onlyTrashed()->findOrFail($id);
        $item->restore();

        $item->load(['patient','visite','soignant:id,name,email']);

        return (new GestionMaladeResource($item))
            ->additional(['restored' => true]);
    }

    // DELETE /api/v1/gestion-malade/{id}/force -> suppression définitive
    public function forceDestroy(string $id)
    {
        $item = GestionMalade::onlyTrashed()->findOrFail($id);
        $item->forceDelete();

        return response()->json([
            'message' => 'Dossier supprimé définitivement.',
            'force_deleted' => true,
            'id' => $id,
        ]);
    }
}
