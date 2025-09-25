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
     * 🔒 soignant_id = medecin_id de la visite (jamais l’utilisateur connecté)
     */
    public function store(GynecologieStoreRequest $request)
    {
        $data = $request->validated();

        // Déduire la visite si absente (dernière visite du patient)
        if (empty($data['visite_id']) && !empty($data['patient_id'])) {
            $data['visite_id'] = Visite::where('patient_id', $data['patient_id'])
                ->orderByDesc('heure_arrivee')
                ->value('id');
        }

        // Recaler depuis la visite
        if (!empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['patient_id']  = $data['patient_id']  ?? $v->patient_id;
                // si la colonne service_id existe sur gynecologies
                if (\Illuminate\Support\Facades\Schema::hasColumn('gynecologies','service_id')) {
                    $data['service_id'] = $data['service_id'] ?? $v->service_id;
                }
                $data['soignant_id'] = $v->medecin_id; // 👈 clé
            }
        }

        // Sécurité: médecin obligatoire
        if (empty($data['soignant_id'])) {
            return response()->json([
                'message' => "Impossible de créer l'acte de gynécologie : aucun médecin n'est associé à la visite."
            ], 422);
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
     * 🔒 soignant_id recalé depuis visite (si visite change)
     */
    public function update(GynecologieUpdateRequest $request, Gynecologie $gynecologie)
    {
        $data = $request->validated();

        // Déduire visite si absente mais patient fourni/existant
        if ((!array_key_exists('visite_id',$data) || empty($data['visite_id'])) && ($data['patient_id'] ?? $gynecologie->patient_id)) {
            $pid = $data['patient_id'] ?? $gynecologie->patient_id;
            $deduced = Visite::where('patient_id', $pid)->orderByDesc('heure_arrivee')->value('id');
            if ($deduced) $data['visite_id'] = $deduced;
        }

        // Si une visite est (re)liée, recaler médecin/patient/service
        if (!empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['soignant_id'] = $v->medecin_id; // 🔒
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id;
                if (\Illuminate\Support\Facades\Schema::hasColumn('gynecologies','service_id')) {
                    $data['service_id']  = $data['service_id']  ?? $v->service_id;
                }
            }
        }

        // Sécurité: ne jamais laisser l’objet sans médecin
        if (empty($data['soignant_id']) && empty($gynecologie->soignant_id)) {
            return response()->json([
                'message' => "Impossible de mettre à jour l'acte de gynécologie : aucun médecin n'est associé à la visite."
            ], 422);
        }

        $gynecologie->fill($data)->save();
        $gynecologie->load(['patient','visite','soignant:id,name,email']);

        return new GynecologieResource($gynecologie);
    }

    /**
     * DELETE /api/v1/gynecologie/{gynecologie}
     */
    public function destroy(Gynecologie $gynecologie)
    {
        $gynecologie->delete();
        return response()->json([
            'message' => 'Acte gynécologie envoyé à la corbeille.',
            'deleted' => true,
            'id'      => $gynecologie->id,
        ], 200);
    }

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

    public function restore(string $id)
    {
        $item = Gynecologie::onlyTrashed()->findOrFail($id);
        $item->restore();

        $item->load(['patient','visite','soignant:id,name,email']);

        return (new GynecologieResource($item))
            ->additional(['restored' => true]);
    }

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
