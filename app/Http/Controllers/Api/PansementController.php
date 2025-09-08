<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PansementStoreRequest;
use App\Http\Requests\PansementUpdateRequest;
use App\Http\Resources\PansementResource;
use App\Models\Pansement;
use App\Models\Visite;
use Illuminate\Http\Request;

class PansementController extends Controller
{
    /**
     * GET /api/v1/pansements
     * Filtres : ?patient_id=…&status=…&q=…&limit=…&sort=-date_soin
     */
    public function index(Request $request)
    {
        $patientId = (string) $request->query('patient_id', '');
        $status    = (string) $request->query('status', '');
        $q         = (string) $request->query('q', '');
        $sortRaw   = (string) $request->query('sort', '-date_soin');

        $field = ltrim($sortRaw, '-');
        $dir   = str_starts_with($sortRaw, '-') ? 'desc' : 'asc';
        if (!in_array($field, ['date_soin', 'created_at', 'type', 'status'], true)) {
            $field = 'date_soin';
        }

        $query = Pansement::query()
            ->with([
                'patient',
                'visite',
                'soignant:id,name,email', // on limite les colonnes
            ])
            ->when($patientId !== '', fn ($q2) => $q2->where('patient_id', $patientId))
            ->when($status !== '', fn ($q2) => $q2->where('status', $status))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($b) use ($q) {
                    $b->where('type', 'like', "%{$q}%")
                      ->orWhere('observation', 'like', "%{$q}%")
                      ->orWhere('etat_plaque', 'like', "%{$q}%")
                      ->orWhere('produits_utilises', 'like', "%{$q}%");
                });
            })
            ->orderBy($field, $dir);

        $perPage = min(max((int) $request->query('limit', 20), 1), 200);
        $items   = $query->paginate($perPage);

        return PansementResource::collection($items)->additional([
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }

    /**
     * POST /api/v1/pansements
     * Crée un pansement. soignant_id = user connecté. visite_id déduite si absente.
     */
    public function store(PansementStoreRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        // soignant = utilisateur connecté
        if ($user) {
            $data['soignant_id'] = $user->id;
        }

        // si la visite n'est pas envoyée, on prend la dernière visite du patient (si elle existe)
        if (empty($data['visite_id']) && !empty($data['patient_id'])) {
            $data['visite_id'] = Visite::where('patient_id', $data['patient_id'])
                ->orderByDesc('heure_arrivee')
                ->value('id');
        }

        // valeurs par défaut (en plus du boot() du modèle)
        $data['date_soin'] = $data['date_soin'] ?? now();
        $data['status']    = $data['status']    ?? 'en_cours';

        $item = Pansement::create($data);

        return (new PansementResource(
            $item->load(['patient', 'visite', 'soignant:id,name,email'])
        ))->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/pansements/{pansement}
     */
    public function show(Pansement $pansement)
    {
        $pansement->load(['patient', 'visite', 'soignant:id,name,email']);
        return new PansementResource($pansement);
    }

    /**
     * PATCH/PUT /api/v1/pansements/{pansement}
     */
    public function update(PansementUpdateRequest $request, Pansement $pansement)
    {
        $data = $request->validated();

        // si la visite n’est pas fournie, on essaie de (re)déduire depuis le patient lié
        if ((!array_key_exists('visite_id', $data) || empty($data['visite_id'])) && ($data['patient_id'] ?? $pansement->patient_id)) {
            $pid = $data['patient_id'] ?? $pansement->patient_id;
            $deducedVisite = Visite::where('patient_id', $pid)
                ->orderByDesc('heure_arrivee')
                ->value('id');
            if ($deducedVisite) {
                $data['visite_id'] = $deducedVisite;
            }
        }

        $pansement->fill($data)->save();

        $pansement->load(['patient', 'visite', 'soignant:id,name,email']);
        return new PansementResource($pansement);
    }

    /**
     * DELETE /api/v1/pansements/{pansement}
     */
    public function destroy(Pansement $pansement)
    {
        $pansement->delete(); // SoftDelete
        return response()->noContent();
    }
}
