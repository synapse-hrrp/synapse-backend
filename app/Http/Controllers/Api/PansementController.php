<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PansementStoreRequest;
use App\Http\Requests\PansementUpdateRequest;
use App\Http\Resources\PansementResource;
use App\Models\Pansement;
use App\Models\Visite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
                // soignant = personnels
                'soignant:id,first_name,last_name,job_title,service_id',
                // 'service' // décommente si tu as ajouté la relation + colonne
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
     * soignant_id/service_id sont déduits de la visite (jamais depuis le client ni l'utilisateur connecté)
     */
    public function store(PansementStoreRequest $request)
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
                // patient/service prennent la valeur de la visite si manquants
                $data['patient_id']  = $data['patient_id']  ?? $v->patient_id;
                if (Schema::hasColumn('pansements','service_id')) {
                    $data['service_id']  = $data['service_id']  ?? $v->service_id;
                }
                // soignant = medecin_id (Personnel) de la visite (obligatoire pour créer)
                $data['soignant_id'] = $v->medecin_id;
            }
        }

        // 3) Sécurité : soignant_id doit être présent (FK personnels)
        if (empty($data['soignant_id'])) {
            return response()->json([
                'message' => "Impossible de créer le pansement : aucun médecin (Personnel) n'est associé à la visite."
            ], 422);
        }

        // 4) Défauts
        $data['date_soin'] = $data['date_soin'] ?? now();
        $data['status']    = $data['status']    ?? 'en_cours';

        // 5) Création
        $item = Pansement::create($data);

        // 6) Retour
        return (new PansementResource(
            $item->load([
                'patient',
                'visite',
                'soignant:id,first_name,last_name,job_title,service_id',
                // 'service'
            ])
        ))->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/pansements/{pansement}
     */
    public function show(Pansement $pansement)
    {
        $pansement->load([
            'patient',
            'visite',
            'soignant:id,first_name,last_name,job_title,service_id',
            // 'service'
        ]);
        return new PansementResource($pansement);
    }

    /**
     * PATCH/PUT /api/v1/pansements/{pansement}
     * Recalque soignant/service/patient si visite_id change
     */
    public function update(PansementUpdateRequest $request, Pansement $pansement)
    {
        $data = $request->validated();

        // Si visite_id est fourni OU si on doit le déduire via patient
        if ((!array_key_exists('visite_id', $data) || empty($data['visite_id'])) && ($data['patient_id'] ?? $pansement->patient_id)) {
            $pid = $data['patient_id'] ?? $pansement->patient_id;
            $deducedVisite = Visite::where('patient_id', $pid)
                ->orderByDesc('heure_arrivee')
                ->value('id');
            if ($deducedVisite) {
                $data['visite_id'] = $deducedVisite;
            }
        }

        // Si on a une visite (nouvelle ou existante), resynchroniser les champs “verrouillés”
        if (!empty($data['visite_id'])) {
            if ($v = Visite::query()->select(['id','patient_id','service_id','medecin_id'])->find($data['visite_id'])) {
                $data['patient_id']  = $data['patient_id'] ?? $v->patient_id;
                if (Schema::hasColumn('pansements','service_id')) {
                    $data['service_id']  = $data['service_id'] ?? $v->service_id;
                }
                $data['soignant_id'] = $v->medecin_id; // re-lock
            }
        }

        // Empêche toute update si on n’a toujours pas de soignant
        if (empty($data['soignant_id']) && empty($pansement->soignant_id)) {
            return response()->json([
                'message' => "Impossible de mettre à jour le pansement : aucun médecin (Personnel) n'est associé à la visite."
            ], 422);
        }

        $pansement->fill($data)->save();

        $pansement->load([
            'patient',
            'visite',
            'soignant:id,first_name,last_name,job_title,service_id',
            // 'service'
        ]);
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
