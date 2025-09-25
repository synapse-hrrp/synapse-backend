<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SmiStoreRequest;
use App\Http\Requests\SmiUpdateRequest;
use App\Http\Resources\SmiResource;
use App\Models\Smi;
use App\Models\Visite;
use Illuminate\Http\Request;

class SmiController extends Controller
{
    // GET /api/v1/smi
    // Filtres: ?patient_id=…&statut=…&q=…&sort=-date_acte&limit=20
    // Corbeille: ?only_trashed=1 | ?with_trashed=1
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

        $query = Smi::query()
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

        return SmiResource::collection($items)->additional([
            'page'         => $items->currentPage(),
            'limit'        => $items->perPage(),
            'total'        => $items->total(),
            'only_trashed' => $onlyTrashed,
            'with_trashed' => $withTrashed,
        ]);
    }

    // POST /api/v1/smi
    public function store(SmiStoreRequest $request)
    {
        $data = $request->validated();

        // 1) Déduire la visite si absente (dernière visite du patient)
        if (empty($data['visite_id']) && !empty($data['patient_id'])) {
            $data['visite_id'] = Visite::where('patient_id', $data['patient_id'])
                ->orderByDesc('heure_arrivee')
                ->value('id');
        }

        // 2) Verrouiller soignant = médecin de la visite
        if (!empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id;
                $data['soignant_id'] = $v->medecin_id;
            }
        }

        // 3) Contrôle bloquant : médecin requis
        if (empty($data['soignant_id'])) {
            return response()->json([
                'message' => "Impossible de créer le SMI : aucun médecin n'est associé à la visite."
            ], 422);
        }

        // 4) Valeurs par défaut
        $data['date_acte'] = $data['date_acte'] ?? now();
        $data['statut']    = $data['statut'] ?? 'en_cours';

        // 5) Idempotence par visite : si un SMI existe déjà pour cette visite, on met à jour
        $item = null;
        if (!empty($data['visite_id'])) {
            $item = Smi::where('visite_id', $data['visite_id'])->first();
        }

        if ($item) {
            $item->fill($data)->save();
        } else {
            $item = Smi::create($data);
        }

        return (new SmiResource(
            $item->load(['patient','visite','soignant:id,name,email'])
        ))->response()->setStatusCode(201);
    }

    // GET /api/v1/smi/{smi}
    public function show(Smi $smi)
    {
        $smi->load(['patient','visite','soignant:id,name,email']);
        return new SmiResource($smi);
    }

    // PATCH/PUT /api/v1/smi/{smi}
    public function update(SmiUpdateRequest $request, Smi $smi)
    {
        $data = $request->validated();

        // Si visite absente, tente de déduire depuis le patient
        if ((!array_key_exists('visite_id',$data) || empty($data['visite_id'])) && ($data['patient_id'] ?? $smi->patient_id)) {
            $pid = $data['patient_id'] ?? $smi->patient_id;
            $deduced = Visite::where('patient_id', $pid)->orderByDesc('heure_arrivee')->value('id');
            if ($deduced) $data['visite_id'] = $deduced;
        }

        // Si on (re)connait la visite, verrouiller le soignant
        if (!empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['soignant_id'] = $v->medecin_id; // tjs médecin de la visite
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id;
            }
        }

        // Médecin doit rester présent
        if (empty($data['soignant_id']) && empty($smi->soignant_id)) {
            return response()->json([
                'message' => "Impossible de mettre à jour le SMI : aucun médecin n'est associé à la visite."
            ], 422);
        }

        $smi->fill($data)->save();
        $smi->load(['patient','visite','soignant:id,name,email']);

        return new SmiResource($smi);
    }

    // DELETE /api/v1/smi/{smi}  → corbeille
    public function destroy(Smi $smi)
    {
        $smi->delete();
        return response()->json([
            'message' => 'Acte SMI envoyé à la corbeille.',
            'deleted' => true,
            'id'      => $smi->id,
        ], 200);
    }

    // GET /api/v1/smi-corbeille → lister la corbeille
    public function trash(Request $request)
    {
        $perPage = min(max((int)$request->query('limit', 20), 1), 200);

        $items = Smi::onlyTrashed()
            ->with(['patient','visite','soignant:id,name,email'])
            ->orderByDesc('deleted_at')
            ->paginate($perPage);

        return SmiResource::collection($items)->additional([
            'trash' => true,
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }

    // POST /api/v1/smi/{id}/restore → restaurer
    public function restore(string $id)
    {
        $item = Smi::onlyTrashed()->findOrFail($id);
        $item->restore();

        $item->load(['patient','visite','soignant:id,name,email']);

        return (new SmiResource($item))
            ->additional(['restored' => true]);
    }

    // DELETE /api/v1/smi/{id}/force → suppression définitive
    public function forceDestroy(string $id)
    {
        $item = Smi::onlyTrashed()->findOrFail($id);
        $item->forceDelete();

        return response()->json([
            'message' => 'Acte SMI supprimé définitivement.',
            'force_deleted' => true,
            'id' => $id,
        ]);
    }
}
