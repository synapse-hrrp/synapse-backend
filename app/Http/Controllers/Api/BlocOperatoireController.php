<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BlocOperatoireStoreRequest;
use App\Http\Requests\BlocOperatoireUpdateRequest;
use App\Http\Resources\BlocOperatoireResource;
use App\Models\BlocOperatoire;
use App\Models\Visite;
use Illuminate\Http\Request;

class BlocOperatoireController extends Controller
{
    // GET /api/v1/bloc-operatoire
    // Filtres: ?patient_id=…&statut=…&q=…&sort=-date_intervention&limit=20
    // Corbeille: ?only_trashed=1 | ?with_trashed=1
    public function index(Request $request)
    {
        $patientId   = (string) $request->query('patient_id', '');
        $statut      = (string) $request->query('statut', '');
        $q           = (string) $request->query('q', '');
        $sortRaw     = (string) $request->query('sort', '-date_intervention');
        $onlyTrashed = $request->boolean('only_trashed', false);
        $withTrashed = $request->boolean('with_trashed', false);

        $field = ltrim($sortRaw, '-');
        $dir   = str_starts_with($sortRaw, '-') ? 'desc' : 'asc';
        if (!in_array($field, ['date_intervention','created_at','statut'], true)) {
            $field = 'date_intervention';
        }

        $query = BlocOperatoire::query()
            ->when($onlyTrashed, fn($q) => $q->onlyTrashed())
            ->when(!$onlyTrashed && $withTrashed, fn($q) => $q->withTrashed())
            ->with(['patient','visite','soignant:id,name,email','chirurgien:id,name,email','anesthesiste:id,name,email','infirmierBloc:id,name,email'])
            ->when($patientId !== '', fn($q2) => $q2->where('patient_id', $patientId))
            ->when($statut !== '', fn($q2) => $q2->where('statut', $statut))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($b) use ($q) {
                    $b->where('type_intervention','like',"%{$q}%")
                      ->orWhere('indication','like',"%{$q}%")
                      ->orWhere('gestes_realises','like',"%{$q}%")
                      ->orWhere('compte_rendu','like',"%{$q}%");
                });
            })
            ->orderBy($field, $dir);

        $perPage = min(max((int)$request->query('limit', 20), 1), 200);
        $items   = $query->paginate($perPage);

        return BlocOperatoireResource::collection($items)->additional([
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
            'only_trashed' => $onlyTrashed,
            'with_trashed' => $withTrashed,
        ]);
    }

    // POST /api/v1/bloc-operatoire
    public function store(BlocOperatoireStoreRequest $request)
    {
        $data = $request->validated();

        // soignant = user connecté
        if ($request->user()) {
            $data['soignant_id'] = $request->user()->id;
        }

        // déduire visite si absente (dernière du patient)
        if (empty($data['visite_id'])) {
            $data['visite_id'] = Visite::where('patient_id', $data['patient_id'])
                ->orderByDesc('heure_arrivee')
                ->value('id');
        }

        $item = BlocOperatoire::create($data);

        return (new BlocOperatoireResource(
            $item->load(['patient','visite','soignant:id,name,email','chirurgien:id,name,email','anesthesiste:id,name,email','infirmierBloc:id,name,email'])
        ))->response()->setStatusCode(201);
    }

    // GET /api/v1/bloc-operatoire/{bloc_operatoire}
    public function show(BlocOperatoire $bloc_operatoire)
    {
        $bloc_operatoire->load(['patient','visite','soignant:id,name,email','chirurgien:id,name,email','anesthesiste:id,name,email','infirmierBloc:id,name,email']);
        return new BlocOperatoireResource($bloc_operatoire);
    }

    // PATCH/PUT /api/v1/bloc-operatoire/{bloc_operatoire}
    public function update(BlocOperatoireUpdateRequest $request, BlocOperatoire $bloc_operatoire)
    {
        $data = $request->validated();

        if ((!array_key_exists('visite_id',$data) || empty($data['visite_id'])) && ($data['patient_id'] ?? $bloc_operatoire->patient_id)) {
            $pid = $data['patient_id'] ?? $bloc_operatoire->patient_id;
            $deduced = Visite::where('patient_id', $pid)->orderByDesc('heure_arrivee')->value('id');
            if ($deduced) $data['visite_id'] = $deduced;
        }

        $bloc_operatoire->fill($data)->save();
        $bloc_operatoire->load(['patient','visite','soignant:id,name,email','chirurgien:id,name,email','anesthesiste:id,name,email','infirmierBloc:id,name,email']);

        return new BlocOperatoireResource($bloc_operatoire);
    }

    // DELETE /api/v1/bloc-operatoire/{bloc_operatoire} -> corbeille
    public function destroy(BlocOperatoire $bloc_operatoire)
    {
        $bloc_operatoire->delete();

        return response()->json([
            'message' => 'Acte bloc opératoire envoyé à la corbeille.',
            'deleted' => true,
            'id'      => $bloc_operatoire->id,
        ], 200);
    }

    // GET /api/v1/bloc-operatoire-corbeille
    public function trash(Request $request)
    {
        $perPage = min(max((int)$request->query('limit', 20), 1), 200);

        $items = BlocOperatoire::onlyTrashed()
            ->with(['patient','visite','soignant:id,name,email','chirurgien:id,name,email','anesthesiste:id,name,email','infirmierBloc:id,name,email'])
            ->orderByDesc('deleted_at')
            ->paginate($perPage);

        return BlocOperatoireResource::collection($items)->additional([
            'trash' => true,
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }

    // POST /api/v1/bloc-operatoire/{id}/restore
    public function restore(string $id)
    {
        $item = BlocOperatoire::onlyTrashed()->findOrFail($id);
        $item->restore();

        $item->load(['patient','visite','soignant:id,name,email','chirurgien:id,name,email','anesthesiste:id,name,email','infirmierBloc:id,name,email']);

        return (new BlocOperatoireResource($item))
            ->additional(['restored' => true]);
    }

    // DELETE /api/v1/bloc-operatoire/{id}/force
    public function forceDestroy(string $id)
    {
        $item = BlocOperatoire::onlyTrashed()->findOrFail($id);
        $item->forceDelete();

        return response()->json([
            'message' => 'Acte bloc opératoire supprimé définitivement.',
            'force_deleted' => true,
            'id' => $id,
        ]);
    }
}
