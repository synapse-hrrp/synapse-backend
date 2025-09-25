<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaterniteStoreRequest;
use App\Http\Requests\MaterniteUpdateRequest;
use App\Http\Resources\MaterniteResource;
use App\Models\Maternite;
use App\Models\Visite;
use Illuminate\Http\Request;

class MaterniteController extends Controller
{
    public function index(Request $request)
    {
        $patientId   = (string) $request->query('patient_id', '');
        $statut      = (string) $request->query('statut', '');
        $q           = (string) $request->query('q', '');
        $sortRaw     = (string) $request->query('sort', '-date_acte');

        $field = ltrim($sortRaw, '-');
        $dir   = str_starts_with($sortRaw, '-') ? 'desc' : 'asc';
        if (!in_array($field, ['date_acte','created_at','statut'], true)) {
            $field = 'date_acte';
        }

        $query = Maternite::query()
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

        return MaterniteResource::collection($items)->additional([
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }

    public function store(MaterniteStoreRequest $request)
    {
        $data = $request->validated();

        // déduire visite si absente
        if (empty($data['visite_id']) && !empty($data['patient_id'])) {
            $data['visite_id'] = Visite::where('patient_id', $data['patient_id'])
                ->orderByDesc('heure_arrivee')
                ->value('id');
        }

        // recaler depuis visite
        if (!empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id;
                if (\Illuminate\Support\Facades\Schema::hasColumn('maternites','service_id')) {
                    $data['service_id'] = $data['service_id'] ?? $v->service_id;
                }
                $data['soignant_id'] = $v->medecin_id; // 👈
            }
        }

        if (empty($data['soignant_id'])) {
            return response()->json([
                'message' => "Impossible de créer l'acte de maternité : aucun médecin n'est associé à la visite."
            ], 422);
        }

        $data['date_acte'] = $data['date_acte'] ?? now();
        $data['statut']    = $data['statut'] ?? 'en_cours';

        $item = Maternite::create($data);

        return (new MaterniteResource(
            $item->load(['patient','visite','soignant:id,name,email'])
        ))->response()->setStatusCode(201);
    }

    public function show(Maternite $maternite)
    {
        $maternite->load(['patient','visite','soignant:id,name,email']);
        return new MaterniteResource($maternite);
    }

    public function update(MaterniteUpdateRequest $request, Maternite $maternite)
    {
        $data = $request->validated();

        if ((!array_key_exists('visite_id',$data) || empty($data['visite_id'])) && ($data['patient_id'] ?? $maternite->patient_id)) {
            $pid = $data['patient_id'] ?? $maternite->patient_id;
            $deduced = Visite::where('patient_id', $pid)->orderByDesc('heure_arrivee')->value('id');
            if ($deduced) $data['visite_id'] = $deduced;
        }

        if (!empty($data['visite_id'])) {
            if ($v = Visite::find($data['visite_id'])) {
                $data['soignant_id'] = $v->medecin_id; // 🔒
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id;
                if (\Illuminate\Support\Facades\Schema::hasColumn('maternites','service_id')) {
                    $data['service_id']  = $data['service_id']  ?? $v->service_id;
                }
            }
        }

        if (empty($data['soignant_id']) && empty($maternite->soignant_id)) {
            return response()->json([
                'message' => "Impossible de mettre à jour l'acte de maternité : aucun médecin n'est associé à la visite."
            ], 422);
        }

        $maternite->fill($data)->save();

        return new MaterniteResource($maternite->load(['patient','visite','soignant:id,name,email']));
    }

    public function destroy(Maternite $maternite)
    {
        $maternite->delete();
        return response()->json([
            'message' => 'Acte maternité envoyé à la corbeille.',
            'deleted' => true,
            'id'      => $maternite->id,
        ], 200);
    }
}
