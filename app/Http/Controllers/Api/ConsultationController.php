<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultationStoreRequest;
use App\Http\Requests\ConsultationUpdateRequest;
use App\Http\Resources\ConsultationResource;
use App\Models\Consultation;
use App\Models\Visite;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    // GET /api/v1/consultations
    public function index(Request $request)
    {
        $patientId   = (string) $request->query('patient_id', '');
        $categorie   = (string) $request->query('categorie', '');
        $type        = (string) $request->query('type', '');
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

        $query = Consultation::query()
            ->when($onlyTrashed, fn($q) => $q->onlyTrashed())
            ->when(!$onlyTrashed && $withTrashed, fn($q) => $q->withTrashed())
            ->with([
                'patient',
                'visite',
                'soignant:id,name,email',
                'medecin:id,name,email',
            ])
            ->when($patientId !== '', fn($q2) => $q2->where('patient_id', $patientId))
            ->when($categorie !== '', fn($q2) => $q2->where('categorie', $categorie))
            ->when($type !== '', fn($q2) => $q2->where('type_consultation', $type))
            ->when($statut !== '', fn($q2) => $q2->where('statut', $statut))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($b) use ($q) {
                    $b->where('motif','like',"%{$q}%")
                      ->orWhere('diagnostic','like',"%{$q}%")
                      ->orWhere('examen_clinique','like',"%{$q}%")
                      ->orWhere('prescriptions','like',"%{$q}%")
                      ->orWhere('orientation_service','like',"%{$q}%");
                });
            })
            ->orderBy($field, $dir);

        $perPage = min(max((int)$request->query('limit', 20), 1), 200);
        $items   = $query->paginate($perPage);

        return ConsultationResource::collection($items)->additional([
            'page'         => $items->currentPage(),
            'limit'        => $items->perPage(),
            'total'        => $items->total(),
            'only_trashed' => $onlyTrashed,
            'with_trashed' => $withTrashed,
        ]);
    }

    // POST /api/v1/consultations
    public function store(ConsultationStoreRequest $request)
    {
        $data = $request->validated();

        // soignant = user connecté (traçabilité)
        if ($request->user()) {
            $data['soignant_id'] = $request->user()->id;
        }

        // déduire la visite si absente
        if (empty($data['visite_id'])) {
            $data['visite_id'] = Visite::where('patient_id', $data['patient_id'])
                ->orderByDesc('heure_arrivee')
                ->value('id');
        }

        // Fallback medecin_id : payload > visite.medecin_id > soignant_id
        if (empty($data['medecin_id'])) {
            $medecinFromVisite = null;
            if (!empty($data['visite_id'])) {
                $medecinFromVisite = Visite::where('id', $data['visite_id'])->value('medecin_id');
            } else {
                $medecinFromVisite = Visite::where('patient_id', $data['patient_id'])
                    ->orderByDesc('heure_arrivee')
                    ->value('medecin_id');
            }
            $data['medecin_id'] = $medecinFromVisite ?: ($data['soignant_id'] ?? null);
        }

        // défauts
        $data['date_acte'] = $data['date_acte'] ?? now();
        $data['statut']    = $data['statut'] ?? 'en_cours';

        $item = Consultation::create($data);

        return (new ConsultationResource(
            $item->load(['patient','visite','soignant:id,name,email','medecin:id,name,email'])
        ))->response()->setStatusCode(201);
    }

    // GET /api/v1/consultations/{consultation}
    public function show(Consultation $consultation)
    {
        $consultation->load(['patient','visite','soignant:id,name,email','medecin:id,name,email']);
        return new ConsultationResource($consultation);
    }

    // PATCH/PUT /api/v1/consultations/{consultation}
    public function update(ConsultationUpdateRequest $request, Consultation $consultation)
    {
        $data = $request->validated();

        // redéduire la visite si absente dans payload (facultatif)
        if ((!array_key_exists('visite_id',$data) || empty($data['visite_id'])) && ($data['patient_id'] ?? $consultation->patient_id)) {
            $pid = $data['patient_id'] ?? $consultation->patient_id;
            $deduced = Visite::where('patient_id', $pid)->orderByDesc('heure_arrivee')->value('id');
            if ($deduced) $data['visite_id'] = $deduced;
        }

        // medecin_id : on n’écrase pas s’il n’est pas envoyé.
        // (Si tu veux appliquer le même fallback que dans store() quand il est absent,
        // dé-commente la section ci-dessous.)
        
        if (!array_key_exists('medecin_id', $data) || empty($data['medecin_id'])) {
            $pid = $data['patient_id'] ?? $consultation->patient_id;
            $vid = $data['visite_id']  ?? $consultation->visite_id;

            $medecinFromVisite = null;
            if ($vid) {
                $medecinFromVisite = Visite::where('id', $vid)->value('medecin_id');
            } else {
                $medecinFromVisite = Visite::where('patient_id', $pid)
                    ->orderByDesc('heure_arrivee')
                    ->value('medecin_id');
            }
            $data['medecin_id'] = $medecinFromVisite ?: $consultation->soignant_id;
        }
        

        $consultation->fill($data)->save();
        $consultation->load(['patient','visite','soignant:id,name,email','medecin:id,name,email']);

        return new ConsultationResource($consultation);
    }

    // DELETE /api/v1/consultations/{consultation}  -> corbeille (soft delete)
    public function destroy(Consultation $consultation)
    {
        $consultation->delete();

        return response()->json([
            'message' => 'Consultation envoyée à la corbeille.',
            'deleted' => true,
            'id'      => $consultation->id,
        ], 200);
    }

    // GET /api/v1/consultations-corbeille
    public function trash(Request $request)
    {
        $perPage = min(max((int)$request->query('limit', 20), 1), 200);

        $items = Consultation::onlyTrashed()
            ->with(['patient','visite','soignant:id,name,email','medecin:id,name,email'])
            ->orderByDesc('deleted_at')
            ->paginate($perPage);

        return ConsultationResource::collection($items)->additional([
            'trash' => true,
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }

    // POST /api/v1/consultations/{id}/restore
    public function restore(string $id)
    {
        $item = Consultation::onlyTrashed()->findOrFail($id);
        $item->restore();

        $item->load(['patient','visite','soignant:id,name,email','medecin:id,name,email']);

        return (new ConsultationResource($item))
            ->additional(['restored' => true]);
    }

    // DELETE /api/v1/consultations/{id}/force
    public function forceDestroy(string $id)
    {
        $item = Consultation::onlyTrashed()->findOrFail($id);
        $item->forceDelete();

        return response()->json([
            'message' => 'Consultation supprimée définitivement.',
            'force_deleted' => true,
            'id' => $id,
        ], 200);
    }
}
