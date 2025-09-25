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
    /**
     * GET /api/v1/sanitaires
     * Filtres: ?patient_id=…&statut=…&q=…&type_action=…&sort=-date_acte&limit=20
     * Corbeille: ?only_trashed=1 | ?with_trashed=1
     */
    public function index(Request $request)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('sanitaire.view'))) {
            return response()->json(['message' => 'Forbidden: sanitaire.view requis'], 403);
        }

        $patientId   = (string) $request->query('patient_id','');
        $statut      = (string) $request->query('statut','');
        $q           = (string) $request->query('q','');
        $typeAction  = (string) $request->query('type_action','');
        $sortRaw     = (string) $request->query('sort','-date_acte');
        $onlyTrashed = $request->boolean('only_trashed', false);
        $withTrashed = $request->boolean('with_trashed', false);

        $field = ltrim($sortRaw, '-');
        $dir   = str_starts_with($sortRaw, '-') ? 'desc' : 'asc';
        if (!in_array($field, ['date_acte','created_at','type_action','statut'], true)) {
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
            'page'         => $items->currentPage(),
            'limit'        => $items->perPage(),
            'total'        => $items->total(),
            'only_trashed' => $onlyTrashed,
            'with_trashed' => $withTrashed,
        ]);
    }

    /**
     * POST /api/v1/sanitaires
     * Règle: si visite présente/déduite → soignant_id = visite.medecin_id
     */
    public function store(SanitaireStoreRequest $request)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('sanitaire.create'))) {
            return response()->json(['message' => 'Forbidden: sanitaire.create requis'], 403);
        }

        $data = $request->validated();

        // Déduire la visite si absente
        if (empty($data['visite_id']) && !empty($data['patient_id'])) {
            $data['visite_id'] = Visite::where('patient_id', $data['patient_id'])
                ->orderByDesc('heure_arrivee')
                ->value('id');
        }

        // Verrouiller le soignant = médecin de la visite si on a une visite
        if (!empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id;
                $data['soignant_id'] = $v->medecin_id; // source de vérité
            }
        }

        // Valeurs par défaut
        $data['date_acte'] = $data['date_acte'] ?? now();
        $data['statut']    = $data['statut'] ?? 'planifie';

        $item = Sanitaire::create($data);

        return (new SanitaireResource(
            $item->load(['patient','visite','soignant:id,name,email'])
        ))->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/sanitaires/{sanitaire}
     */
    public function show(Request $request, Sanitaire $sanitaire)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('sanitaire.view'))) {
            return response()->json(['message' => 'Forbidden: sanitaire.view requis'], 403);
        }

        return new SanitaireResource($sanitaire->load(['patient','visite','soignant:id,name,email']));
    }

    /**
     * PATCH /api/v1/sanitaires/{sanitaire}
     */
    public function update(SanitaireUpdateRequest $request, Sanitaire $sanitaire)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('sanitaire.update'))) {
            return response()->json(['message' => 'Forbidden: sanitaire.update requis'], 403);
        }

        $data = $request->validated();

        // Si visite absente, tenter de (re)déduire depuis le patient
        if ((!array_key_exists('visite_id', $data) || empty($data['visite_id'])) && ($data['patient_id'] ?? $sanitaire->patient_id)) {
            $pid = $data['patient_id'] ?? $sanitaire->patient_id;
            $deduced = Visite::where('patient_id', $pid)->orderByDesc('heure_arrivee')->value('id');
            if ($deduced) $data['visite_id'] = $deduced;
        }

        // Recalage du soignant si la visite est (ré)renseignée
        if (!empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['soignant_id'] = $v->medecin_id;
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id;
            }
        }

        $sanitaire->fill($data)->save();

        return new SanitaireResource($sanitaire->load(['patient','visite','soignant:id,name,email']));
    }

    /**
     * DELETE /api/v1/sanitaires/{sanitaire}
     * → corbeille
     */
    public function destroy(Request $request, Sanitaire $sanitaire)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('sanitaire.delete'))) {
            return response()->json(['message' => 'Forbidden: sanitaire.delete requis'], 403);
        }

        $sanitaire->delete();
        return response()->noContent();
    }

    /**
     * GET /api/v1/sanitaires-corbeille
     */
    public function trash(Request $request)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('sanitaire.view'))) {
            return response()->json(['message' => 'Forbidden: sanitaire.view requis'], 403);
        }

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

    /**
     * POST /api/v1/sanitaires/{id}/restore
     */
    public function restore(Request $request, string $id)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('sanitaire.update'))) {
            return response()->json(['message' => 'Forbidden: sanitaire.update requis'], 403);
        }

        $item = Sanitaire::onlyTrashed()->findOrFail($id);
        $item->restore();

        return (new SanitaireResource($item->load(['patient','visite','soignant:id,name,email'])))
            ->additional(['restored' => true]);
    }

    /**
     * DELETE /api/v1/sanitaires/{id}/force
     */
    public function forceDestroy(Request $request, string $id)
    {
        $u = $request->user();
        if (!$u || (!$u->tokenCan('*') && !$u->tokenCan('sanitaire.delete'))) {
            return response()->json(['message' => 'Forbidden: sanitaire.delete requis'], 403);
        }

        $item = Sanitaire::onlyTrashed()->findOrFail($id);
        $item->forceDelete();

        return response()->json([
            'message' => 'Acte sanitaire supprimé définitivement.',
            'force_deleted' => true,
            'id' => $id,
        ]);
    }
}
