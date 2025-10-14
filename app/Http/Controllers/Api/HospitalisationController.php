<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHospitalisationRequest;
use App\Http\Requests\UpdateHospitalisationRequest;
use App\Http\Resources\HospitalisationResource;
use App\Models\Hospitalisation;
use App\Models\Service;
use Illuminate\Http\Request;

class HospitalisationController extends Controller
{
    public function index(Request $request)
    {
        $q = Hospitalisation::query()
            ->with(['patient','service','medecinTraitant'])
            ->latest('date_admission');

        // filtres
        if ($request->filled('patient_id'))   $q->where('patient_id', $request->patient_id);
        if ($request->filled('service_slug')) $q->where('service_slug', $request->service_slug);
        if ($request->filled('statut'))       $q->where('statut', $request->statut);
        if ($request->filled('from'))         $q->whereDate('date_admission', '>=', $request->from);
        if ($request->filled('to'))           $q->whereDate('date_admission', '<=', $request->to);
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function($qq) use ($s) {
                $qq->where('admission_no', 'like', "%$s%")
                   ->orWhere('motif_admission', 'like', "%$s%")
                   ->orWhere('diagnostic_entree', 'like', "%$s%")
                   ->orWhere('diagnostic_sortie', 'like', "%$s%");
            });
        }

        return HospitalisationResource::collection(
            $q->paginate($request->get('per_page', 20))->appends($request->query())
        );
    }

    public function show(Hospitalisation $hospitalisation)
    {
        $hospitalisation->load(['patient','service','medecinTraitant']);
        return new HospitalisationResource($hospitalisation);
    }

    public function store(StoreHospitalisationRequest $request)
    {
        $data = $request->validated();
        $data['created_by_user_id'] = $request->user()?->id;

        $hosp = Hospitalisation::create($data);
        $hosp->load(['patient','service','medecinTraitant']);

        return (new HospitalisationResource($hosp))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateHospitalisationRequest $request, Hospitalisation $hospitalisation)
    {
        $hospitalisation->fill($request->validated())->save();
        $hospitalisation->load(['patient','service','medecinTraitant']);
        return new HospitalisationResource($hospitalisation);
    }

    public function destroy(Hospitalisation $hospitalisation)
    {
        $hospitalisation->delete();
        return response()->json(['message' => 'Hospitalisation supprimée.']);
    }

    public function restore($id)
    {
        $hosp = Hospitalisation::withTrashed()->findOrFail($id);
        $hosp->restore();
        $hosp->load(['patient','service','medecinTraitant']);
        return new HospitalisationResource($hosp);
    }

    // création “depuis un service”
    public function storeForService(StoreHospitalisationRequest $request, Service $service)
    {
        $data = $request->validated();
        $data['service_slug']       = $service->slug;
        $data['created_by_user_id'] = $request->user()?->id;

        $hosp = Hospitalisation::create($data);
        $hosp->load(['patient','service','medecinTraitant']);

        return (new HospitalisationResource($hosp))
            ->response()
            ->setStatusCode(201);
    }
}
