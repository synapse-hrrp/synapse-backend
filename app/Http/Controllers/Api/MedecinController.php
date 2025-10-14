<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medecin;
use App\Models\Personnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// NEW: Requests & Resource
use App\Http\Requests\MedecinStoreRequest;
use App\Http\Requests\MedecinUpdateRequest;
use App\Http\Resources\MedecinResource;

class MedecinController extends Controller
{
    /**
     * GET /api/v1/medecins
     * ?search=...&specialite=...&with=personnel,user&per_page=...
     */
    public function index(Request $request)
    {
        $with = collect(explode(',', (string) $request->query('with')))
            ->filter()
            ->map(fn ($rel) => trim($rel))
            ->intersect(['personnel', 'personnel.user'])
            ->values()
            ->all();

        $perPage = (int) $request->integer('per_page', 15);

        $items = Medecin::query()
            ->when(!empty($with), fn ($q) => $q->with($with))
            ->when($request->filled('specialite'), fn ($q) => $q->bySpecialite($request->string('specialite')))
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')))
            ->latest('id')
            ->paginate($perPage)
            ->appends($request->query());

        return MedecinResource::collection($items);
    }

    /**
     * POST /api/v1/medecins
     * Body: personnel_id, numero_ordre, specialite, grade?
     */
    public function store(MedecinStoreRequest $request)
    {
        $data = $request->validated();

        $medecin = DB::transaction(function () use ($data) {
            // (facultatif) on vérifie que le personnel existe et charge son user
            Personnel::with('user')->findOrFail($data['personnel_id']);
            return Medecin::create($data);
        });

        return (new MedecinResource($medecin->load('personnel.user')))
            ->additional(['message' => 'Médecin créé avec succès.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/medecins/{medecin}?with=personnel,user
     */
    public function show(Request $request, Medecin $medecin)
    {
        $with = collect(explode(',', (string) $request->query('with')))
            ->filter()
            ->map(fn ($rel) => trim($rel))
            ->intersect(['personnel', 'personnel.user'])
            ->values()
            ->all();

        if (!empty($with)) {
            $medecin->load($with);
        }

        return new MedecinResource($medecin);
    }

    /**
     * PUT/PATCH /api/v1/medecins/{medecin}
     */
    public function update(MedecinUpdateRequest $request, Medecin $medecin)
    {
        DB::transaction(function () use ($medecin, $request) {
            $medecin->update($request->validated());
        });

        return (new MedecinResource($medecin->fresh('personnel.user')))
            ->additional(['message' => 'Médecin mis à jour.']);
    }

    /**
     * DELETE /api/v1/medecins/{medecin}
     */
    public function destroy(Medecin $medecin)
    {
        $medecin->delete(); // soft delete si activé dans le modèle/migration
        return response()->json(['message' => 'Médecin supprimé.']);
    }

    /**
     * GET /api/v1/medecins-corbeille
     * (nécessite SoftDeletes sur le modèle + migration)
     */
    public function trash(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 15);

        $items = Medecin::onlyTrashed()
            ->with('personnel.user')
            ->latest('id')
            ->paginate($perPage)
            ->appends($request->query());

        return MedecinResource::collection($items);
    }

    /**
     * POST /api/v1/medecins/{id}/restore
     */
    public function restore($id)
    {
        $med = Medecin::onlyTrashed()->findOrFail($id);
        $med->restore();

        return (new MedecinResource($med->fresh('personnel.user')))
            ->additional(['message' => 'Médecin restauré.']);
    }

    /**
     * DELETE /api/v1/medecins/{id}/force
     */
    public function forceDestroy($id)
    {
        $med = Medecin::onlyTrashed()->findOrFail($id);
        $med->forceDelete();

        return response()->json(['message' => 'Médecin supprimé définitivement.']);
    }

    /**
     * GET /api/v1/medecins/by-personnel/{personnel}
     */
    public function byPersonnel(Personnel $personnel)
    {
        $med = $personnel->medecin()->with('personnel.user')->firstOrFail();
        return new MedecinResource($med);
    }

    /**
     * GET /api/v1/me/medecin
     */
    public function me(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $med = optional($user->personnel)->medecin;
        if (!$med) {
            return response()->json(['message' => 'Aucun profil médecin pour cet utilisateur.'], 404);
        }

        return new MedecinResource($med->load('personnel.user'));
    }
}
