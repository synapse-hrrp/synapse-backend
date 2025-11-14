<?php

namespace App\Http\Controllers\Api;
 
use App\Http\Controllers\Controller;
use App\Http\Requests\AruStoreRequest;
use App\Http\Requests\AruUpdateRequest;
use App\Http\Resources\AruResource;
use App\Models\Aru;
use App\Models\Visite;
use Illuminate\Http\Request;

class AruController extends Controller
{
    // GET /api/v1/aru
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

        $query = Aru::query()
            ->when($onlyTrashed, fn($q) => $q->onlyTrashed())
            ->when(!$onlyTrashed && $withTrashed, fn($q) => $q->withTrashed())
            // soignant = Personnel, pas User
            ->with([
                'patient',
                'visite',
                'soignant:id,first_name,last_name,job_title,service_id'
            ])
            ->when($patientId !== '', fn($q2) => $q2->where('patient_id', $patientId))
            ->when($statut !== '', fn($q2) => $q2->where('statut', $statut))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($b) use ($q) {
                    $b->where('motif','like',"%{$q}%")
                      ->orWhere('examens_complementaires','like',"%{$q}%")
                      ->orWhere('traitements','like',"%{$q}%")
                      ->orWhere('observation','like',"%{$q}%");
                });
            })
            ->orderBy($field, $dir);

        $perPage = min(max((int)$request->query('limit', 20), 1), 200);
        $items   = $query->paginate($perPage);

        return AruResource::collection($items)->additional([
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
            'only_trashed' => $onlyTrashed,
            'with_trashed' => $withTrashed,
        ]);
    }

    // POST /api/v1/aru
    public function store(AruStoreRequest $request)
    {
        $data = $request->validated();

        // ❌ ne jamais accepter soignant_id depuis le client
        unset($data['soignant_id']);

        // Déduire visite si absente
        if (empty($data['visite_id']) && !empty($data['patient_id'])) {
            $data['visite_id'] = Visite::where('patient_id', $data['patient_id'])
                ->orderByDesc('heure_arrivee')
                ->value('id');
        }

        // Imposer les valeurs cohérentes depuis la visite
        if (!empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['patient_id']  = $data['patient_id']  ?? $v->patient_id;
                $data['service_id']  = $data['service_id']  ?? $v->service_id;
                $data['soignant_id'] = $v->medecin_id; // médecin = Personnel
            }
        }

        if (empty($data['soignant_id'])) {
            return response()->json([
                'message' => "Impossible de créer l'ARU : aucun médecin (Personnel) n'est associé à la visite."
            ], 422);
        }

        $data['date_acte'] = $data['date_acte'] ?? now();
        $data['statut']    = $data['statut'] ?? 'en_cours';

        // Idempotence : si déjà créé par l'Observer Visite, on met à jour
        $item = null;
        if (!empty($data['visite_id'])) {
            $item = Aru::where('visite_id', $data['visite_id'])->first();
        }

        if ($item) {
            $item->fill($data)->save();
        } else {
            $item = Aru::create($data);
        }

        return (new AruResource(
            $item->load(['patient','visite','soignant:id,first_name,last_name,job_title,service_id'])
        ))->response()->setStatusCode(201);
    }

    // GET /api/v1/aru/{aru}
    public function show(Aru $aru)
    {
        $aru->load(['patient','visite','soignant:id,first_name,last_name,job_title,service_id']);
        return new AruResource($aru);
    }

    // PATCH/PUT /api/v1/aru/{aru}
    public function update(AruUpdateRequest $request, Aru $aru)
    {
        $data = $request->validated();

        // ❌ ne jamais accepter soignant_id depuis le client
        unset($data['soignant_id']);

        // Déduire visite la plus récente si besoin
        if ((!array_key_exists('visite_id',$data) || empty($data['visite_id'])) && ($data['patient_id'] ?? $aru->patient_id)) {
            $pid = $data['patient_id'] ?? $aru->patient_id;
            $deduced = Visite::where('patient_id', $pid)->orderByDesc('heure_arrivee')->value('id');
            if ($deduced) $data['visite_id'] = $deduced;
        }

        // Verrouiller soignant = médecin de la visite
        if (!empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['soignant_id'] = $v->medecin_id;
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id;
                $data['service_id']  = $data['service_id'] ?? $v->service_id;
            }
        }

        if (empty($data['soignant_id']) && empty($aru->soignant_id)) {
            return response()->json([
                'message' => "Impossible de mettre à jour l'ARU : aucun médecin (Personnel) n'est associé à la visite."
            ], 422);
        }

        $aru->fill($data)->save();
        $aru->load(['patient','visite','soignant:id,first_name,last_name,job_title,service_id']);

        return new AruResource($aru);
    }

    public function destroy(Aru $aru)
    {
        $aru->delete();

        return response()->json([
            'message' => 'Acte d’ARU envoyé à la corbeille.',
            'deleted' => true,
            'id'      => $aru->id,
        ], 200);
    }

    public function trash(Request $request)
    {
        $perPage = min(max((int)$request->query('limit', 20), 1), 200);

        $items = Aru::onlyTrashed()
            ->with(['patient','visite','soignant:id,first_name,last_name,job_title,service_id'])
            ->orderByDesc('deleted_at')
            ->paginate($perPage);

        return AruResource::collection($items)->additional([
            'trash' => true,
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }

    public function restore(string $id)
    {
        $item = Aru::onlyTrashed()->findOrFail($id);
        $item->restore();

        $item->load(['patient','visite','soignant:id,first_name,last_name,job_title,service_id']);

        return (new AruResource($item))
            ->additional(['restored' => true]);
    }

    public function forceDestroy(string $id)
    {
        $item = Aru::onlyTrashed()->findOrFail($id);
        $item->forceDelete();

        return response()->json([
            'message' => 'Acte d’ARU supprimé définitivement.',
            'force_deleted' => true,
            'id' => $id,
        ]);
    }
}
