<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\KinesitherapieStoreRequest;
use App\Http\Requests\KinesitherapieUpdateRequest;
use App\Http\Resources\KinesitherapieResource;
use App\Models\Kinesitherapie;
use App\Models\Visite;
use Illuminate\Http\Request;

class KinesitherapieController extends Controller
{
    // GET /api/v1/kinesitherapie
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

        $query = Kinesitherapie::query()
            ->when($onlyTrashed, fn($q) => $q->onlyTrashed())
            ->when(!$onlyTrashed && $withTrashed, fn($q) => $q->withTrashed())
            ->with([
                'patient',
                'visite',
                // soignant = Personnel (+ user optionnel pour name/email)
                'soignant.user',
            ])
            ->when($patientId !== '', fn($q2) => $q2->where('patient_id', $patientId))
            ->when($statut !== '', fn($q2) => $q2->where('statut', $statut))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($b) use ($q) {
                    $b->where('motif','like',"%{$q}%")
                      ->orWhere('diagnostic','like',"%{$q}%")
                      ->orWhere('evaluation','like',"%{$q}%")
                      ->orWhere('objectifs','like',"%{$q}%")
                      ->orWhere('techniques','like',"%{$q}%")
                      ->orWhere('zone_traitee','like',"%{$q}%")
                      ->orWhere('resultats','like',"%{$q}%")
                      ->orWhere('conseils','like',"%{$q}%");
                });
            })
            ->orderBy($field, $dir);

        $perPage = min(max((int)$request->query('limit', 20), 1), 200);
        $items   = $query->paginate($perPage);

        return KinesitherapieResource::collection($items)->additional([
            'page'         => $items->currentPage(),
            'limit'        => $items->perPage(),
            'total'        => $items->total(),
            'only_trashed' => $onlyTrashed,
            'with_trashed' => $withTrashed,
        ]);
    }

    // POST /api/v1/kinesitherapie
    public function store(KinesitherapieStoreRequest $request)
    {
        $data = $request->validated();

        // Déduire la visite si absente à partir du patient
        if (empty($data['visite_id']) && !empty($data['patient_id'])) {
            $data['visite_id'] = Visite::where('patient_id', $data['patient_id'])
                ->orderByDesc('heure_arrivee')
                ->value('id');
        }

        // Si visite fournie/déduite, verrouiller soignant_id = medecin_id de la visite
        if (!empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id; // cohérence
                $data['soignant_id'] = $v->medecin_id; // Personnel.id
            }
        }

        // Sécurité : il faut un médecin
        if (empty($data['soignant_id'])) {
            return response()->json([
                'message' => "Impossible de créer l'acte de kinésithérapie : aucun médecin n'est associé à la visite."
            ], 422);
        }

        // Defaults
        $data['date_acte'] = $data['date_acte'] ?? now();
        $data['statut']    = $data['statut'] ?? 'planifie';

        // Idempotence : un seul enregistrement par visite (si c’est ta règle)
        $item = null;
        if (!empty($data['visite_id'])) {
            $item = Kinesitherapie::where('visite_id', $data['visite_id'])->first();
        }

        if ($item) {
            $item->fill($data)->save();
        } else {
            $item = Kinesitherapie::create($data);
        }

        return (new KinesitherapieResource(
            $item->load(['patient','visite','soignant.user'])
        ))->response()->setStatusCode(201);
    }

    // GET /api/v1/kinesitherapie/{kinesitherapie}
    public function show(Kinesitherapie $kinesitherapie)
    {
        $kinesitherapie->load(['patient','visite','soignant.user']);
        return new KinesitherapieResource($kinesitherapie);
    }

    // PATCH/PUT /api/v1/kinesitherapie/{kinesitherapie}
    public function update(KinesitherapieUpdateRequest $request, Kinesitherapie $kinesitherapie)
    {
        $data = $request->validated();

        // Déduire la visite si manquante à partir du patient
        if ((!array_key_exists('visite_id',$data) || empty($data['visite_id'])) && ($data['patient_id'] ?? $kinesitherapie->patient_id)) {
            $pid = $data['patient_id'] ?? $kinesitherapie->patient_id;
            $deduced = Visite::where('patient_id', $pid)->orderByDesc('heure_arrivee')->value('id');
            if ($deduced) $data['visite_id'] = $deduced;
        }

        // Si on a une visite (nouvelle ou existante), verrouiller soignant_id = medecin_id
        if (!empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id;
                $data['soignant_id'] = $v->medecin_id;
            }
        }

        // Sécurité : si après merge on n’a toujours pas de soignant
        if (empty($data['soignant_id']) && empty($kinesitherapie->soignant_id)) {
            return response()->json([
                'message' => "Impossible de mettre à jour : aucun médecin n'est associé à la visite."
            ], 422);
        }

        $kinesitherapie->fill($data)->save();
        $kinesitherapie->load(['patient','visite','soignant.user']);

        return new KinesitherapieResource($kinesitherapie);
    }

    // DELETE /api/v1/kinesitherapie/{kinesitherapie} -> corbeille
    public function destroy(Kinesitherapie $kinesitherapie)
    {
        $kinesitherapie->delete();

        return response()->json([
            'message' => 'Acte de kinésithérapie envoyé à la corbeille.',
            'deleted' => true,
            'id'      => $kinesitherapie->id,
        ], 200);
    }

    // GET /api/v1/kinesitherapie-corbeille -> liste corbeille
    public function trash(Request $request)
    {
        $perPage = min(max((int)$request->query('limit', 20), 1), 200);

        $items = Kinesitherapie::onlyTrashed()
            ->with(['patient','visite','soignant.user'])
            ->orderByDesc('deleted_at')
            ->paginate($perPage);

        return KinesitherapieResource::collection($items)->additional([
            'trash' => true,
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }

    // POST /api/v1/kinesitherapie/{id}/restore -> restaure
    public function restore(string $id)
    {
        $item = Kinesitherapie::onlyTrashed()->findOrFail($id);
        $item->restore();

        $item->load(['patient','visite','soignant.user']);

        return (new KinesitherapieResource($item))
            ->additional(['restored' => true]);
    }

    // DELETE /api/v1/kinesitherapie/{id}/force -> suppression définitive
    public function forceDestroy(string $id)
    {
        $item = Kinesitherapie::onlyTrashed()->findOrFail($id);
        $item->forceDelete();

        return response()->json([
            'message' => 'Acte de kinésithérapie supprimé définitivement.',
            'force_deleted' => true,
            'id' => $id,
        ]);
    }
}
