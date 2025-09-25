<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PediatrieStoreRequest;
use App\Http\Requests\PediatrieUpdateRequest;
use App\Http\Resources\PediatrieResource;
use App\Models\Pediatrie;
use App\Models\Visite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PediatrieController extends Controller
{
    /**
     * GET /api/v1/pediatrie
     * Filtres: ?patient_id=…&statut=…&q=…&sort=-date_acte&limit=20
     * Options: ?only_trashed=1 | ?with_trashed=1
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

        $query = Pediatrie::query()
            ->when($onlyTrashed, fn($q) => $q->onlyTrashed())
            ->when(!$onlyTrashed && $withTrashed, fn($q) => $q->withTrashed())
            ->with([
                'patient',
                'visite',
                // soignant = personnels
                'soignant:id,first_name,last_name,job_title,service_id',
                // 'service', // décommente si tu as ajouté la colonne + relation
            ])
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

        return PediatrieResource::collection($items)->additional([
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
            'only_trashed' => $onlyTrashed,
            'with_trashed' => $withTrashed,
        ]);
    }

    /**
     * POST /api/v1/pediatrie
     * soignant_id/service_id sont déduits de la visite (jamais depuis le client ni l'utilisateur connecté)
     */
    public function store(PediatrieStoreRequest $request)
    {
        $data = $request->validated();

        // 1) Déduire la visite si absente (dernière visite du patient)
        if (empty($data['visite_id']) && !empty($data['patient_id'])) {
            $data['visite_id'] = Visite::where('patient_id', $data['patient_id'])
                ->orderByDesc('heure_arrivee')
                ->value('id');
        }

        // 2) Si on a une visite, verrouiller les champs depuis la visite
        if (!empty($data['visite_id'])) {
            if ($v = Visite::query()->select(['id','patient_id','service_id','medecin_id'])->find($data['visite_id'])) {
                $data['patient_id']  = $data['patient_id']  ?? $v->patient_id;
                if (Schema::hasColumn('pediatries','service_id')) {
                    $data['service_id']  = $data['service_id']  ?? $v->service_id;
                }
                // soignant = medecin_id (Personnel) de la visite (obligatoire pour créer)
                $data['soignant_id'] = $v->medecin_id;
            }
        }

        // 3) Sécurité : soignant_id doit être présent (FK personnels)
        if (empty($data['soignant_id'])) {
            return response()->json([
                'message' => "Impossible de créer l'acte Pédiatrie : aucun médecin (Personnel) n'est associé à la visite."
            ], 422);
        }

        // 4) Défauts
        $data['date_acte'] = $data['date_acte'] ?? now();
        $data['statut']    = $data['statut'] ?? 'en_cours';

        // 5) Création
        $item = Pediatrie::create($data);

        // 6) Retour
        return (new PediatrieResource(
            $item->load([
                'patient',
                'visite',
                'soignant:id,first_name,last_name,job_title,service_id',
                // 'service'
            ])
        ))->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/pediatrie/{pediatrie}
     */
    public function show(Pediatrie $pediatrie)
    {
        $pediatrie->load([
            'patient',
            'visite',
            'soignant:id,first_name,last_name,job_title,service_id',
            // 'service'
        ]);
        return new PediatrieResource($pediatrie);
    }

    /**
     * PATCH/PUT /api/v1/pediatrie/{pediatrie}
     * Recalque soignant/service/patient si visite_id change
     */
    public function update(PediatrieUpdateRequest $request, Pediatrie $pediatrie)
    {
        $data = $request->validated();

        // Si visite_id est manquant, on peut le déduire via patient
        if ((!array_key_exists('visite_id',$data) || empty($data['visite_id'])) && ($data['patient_id'] ?? $pediatrie->patient_id)) {
            $pid = $data['patient_id'] ?? $pediatrie->patient_id;
            $deduced = Visite::where('patient_id', $pid)->orderByDesc('heure_arrivee')->value('id');
            if ($deduced) $data['visite_id'] = $deduced;
        }

        // Si on a une visite (nouvelle ou existante), resynchroniser les champs “verrouillés”
        if (!empty($data['visite_id'])) {
            if ($v = Visite::query()->select(['id','patient_id','service_id','medecin_id'])->find($data['visite_id'])) {
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id;
                if (Schema::hasColumn('pediatries','service_id')) {
                    $data['service_id']  = $data['service_id'] ?? $v->service_id;
                }
                $data['soignant_id'] = $v->medecin_id; // re-lock
            }
        }

        // Empêche toute update si on n’a toujours pas de soignant
        if (empty($data['soignant_id']) && empty($pediatrie->soignant_id)) {
            return response()->json([
                'message' => "Impossible de mettre à jour l'acte Pédiatrie : aucun médecin (Personnel) n'est associé à la visite."
            ], 422);
        }

        $pediatrie->fill($data)->save();

        $pediatrie->load([
            'patient',
            'visite',
            'soignant:id,first_name,last_name,job_title,service_id',
            // 'service'
        ]);

        return new PediatrieResource($pediatrie);
    }

    /**
     * DELETE /api/v1/pediatrie/{pediatrie}
     * Soft delete + message
     */
    public function destroy(Pediatrie $pediatrie)
    {
        $pediatrie->delete();
        return response()->json([
            'message' => 'Acte pédiatrie envoyé à la corbeille.',
            'deleted' => true,
            'id'      => $pediatrie->id,
        ], 200);
    }

    /**
     * GET /api/v1/pediatrie-corbeille
     */
    public function trash(Request $request)
    {
        $perPage = min(max((int)$request->query('limit', 20), 1), 200);

        $items = Pediatrie::onlyTrashed()
            ->with([
                'patient',
                'visite',
                'soignant:id,first_name,last_name,job_title,service_id',
                // 'service'
            ])
            ->orderByDesc('deleted_at')
            ->paginate($perPage);

        return PediatrieResource::collection($items)->additional([
            'trash' => true,
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }

    /**
     * POST /api/v1/pediatrie/{id}/restore
     */
    public function restore(string $id)
    {
        $item = Pediatrie::onlyTrashed()->findOrFail($id);
        $item->restore();

        $item->load([
            'patient',
            'visite',
            'soignant:id,first_name,last_name,job_title,service_id',
            // 'service'
        ]);

        return (new PediatrieResource($item))
            ->additional(['restored' => true]);
    }

    /**
     * DELETE /api/v1/pediatrie/{id}/force
     */
    public function forceDestroy(string $id)
    {
        $item = Pediatrie::onlyTrashed()->findOrFail($id);
        $item->forceDelete();

        return response()->json([
            'message' => 'Acte pédiatrie supprimé définitivement.',
            'force_deleted' => true,
            'id' => $id,
        ]);
    }
}
