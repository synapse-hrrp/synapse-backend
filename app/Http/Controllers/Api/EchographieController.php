<?php

namespace App\Http\Controllers\Api;

use app\Http\Controllers\Controller;
use App\Http\Requests\StoreEchographieRequest;
use App\Http\Requests\UpdateEchographieRequest;
use App\Http\Resources\EchographieResource;
use App\Models\Echographie;
use Illuminate\Http\Request;

class EchographieController extends Controller
{
    /**
     * Liste paginée des échographies
     */
    public function index(Request $request)
    {
        $query = Echographie::query()
            ->with(['patient','service','demandeur','operateur','validateur'])
            ->latest();

        // filtrage optionnel (par statut, service, patient, date…)
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('service_slug')) {
            $query->where('service_slug', $request->service_slug);
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom_echo', 'like', "%$search%")
                  ->orWhere('code_echo', 'like', "%$search%")
                  ->orWhere('indication', 'like', "%$search%");
            });
        }

        $echographies = $query->paginate($request->get('per_page', 20))
                              ->appends($request->query());

        return EchographieResource::collection($echographies);
    }

    /**
     * Création d'une échographie
     */
    public function store(StoreEchographieRequest $request)
    {
        $echographie = Echographie::create($request->validated());

        $echographie->load(['patient','service','demandeur','operateur','validateur']);

        return (new EchographieResource($echographie))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Afficher une échographie donnée
     */
    public function show(Echographie $echographie)
    {
        $echographie->load(['patient','service','demandeur','operateur','validateur']);
        return new EchographieResource($echographie);
    }

    /**
     * Mettre à jour une échographie
     */
    public function update(UpdateEchographieRequest $request, Echographie $echographie)
    {
        $echographie->fill($request->validated());
        $echographie->save();

        $echographie->load(['patient','service','demandeur','operateur','validateur']);
        return new EchographieResource($echographie);
    }

    /**
     * Suppression logique (soft delete)
     */
    public function destroy(Echographie $echographie)
    {
        $echographie->delete();
        return response()->json(['message' => 'Echographie supprimée avec succès.']);
    }

    /**
     * Optionnel : restaurer une échographie supprimée
     */
    public function restore($id)
    {
        $echographie = Echographie::withTrashed()->findOrFail($id);
        $echographie->restore();
        return new EchographieResource($echographie);
    }
}
