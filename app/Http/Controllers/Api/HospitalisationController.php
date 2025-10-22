<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHospitalisationRequest;
use App\Http\Requests\UpdateHospitalisationRequest;
use App\Http\Resources\HospitalisationResource;
use App\Models\Hospitalisation;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class HospitalisationController extends Controller
{
    public function index(Request $request)
    {
        $q = Hospitalisation::query()
            ->with(['patient','service','medecinTraitant'])
            ->latest('date_admission');

        if ($request->filled('patient_id'))   $q->where('patient_id', $request->patient_id);
        if ($request->filled('service_slug')) $q->where('service_slug', $request->service_slug);
        if ($request->filled('statut'))       $q->where('statut', $request->statut);
        if ($request->filled('from'))         $q->whereDate('date_admission', '>=', $request->from);
        if ($request->filled('to'))           $q->whereDate('date_admission', '<=', $request->to);
        if ($request->filled('search')) {
            $s = trim($request->search);
            $q->where(function($qq) use ($s) {
                $qq->where('admission_no', 'like', "%$s%")
                   ->orWhere('motif_admission', 'like', "%$s%")
                   ->orWhere('diagnostic_entree', 'like', "%$s%")
                   ->orWhere('diagnostic_sortie', 'like', "%$s%");
            });
        }

        $perPage = (int) $request->get('per_page', 20);

        return HospitalisationResource::collection(
            $q->paginate($perPage)->appends($request->query())
        );
    }

    public function show(Hospitalisation $hospitalisation)
    {
        $hospitalisation->load(['patient','service','medecinTraitant']);
        return new HospitalisationResource($hospitalisation);
    }

    public function store(StoreHospitalisationRequest $request)
    {
        Log::info('HospitalisationController@store', ['payload' => $request->all()]);

        $data = $request->validated();
        $data['created_by_user_id'] = $request->user()?->id;

        $model = DB::transaction(function () use ($data) {
            $m = Hospitalisation::create($data);
            app(\App\Services\InvoiceService::class)->attachHospitalisation($m);
            return $m->fresh();
        });

        $model->load(['patient','service','medecinTraitant']);

        return (new HospitalisationResource($model))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
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

    public function restore(string $id)
    {
        $hosp = Hospitalisation::withTrashed()->findOrFail($id);
        $hosp->restore();
        $hosp->load(['patient','service','medecinTraitant']);
        return new HospitalisationResource($hosp);
    }

    public function storeForService(StoreHospitalisationRequest $request, Service $service)
    {
        Log::info('HospitalisationController@storeForService', [
            'payload' => $request->all(),
            'service' => $service->slug,
        ]);

        $data = $request->validated();
        $data['service_slug']       = $service->slug;
        $data['created_by_user_id'] = $request->user()?->id;

        $model = DB::transaction(function () use ($data) {
            $m = Hospitalisation::create($data);
            app(\App\Services\InvoiceService::class)->attachHospitalisation($m);
            return $m->fresh();
        });

        $model->load(['patient','service','medecinTraitant']);

        return (new HospitalisationResource($model))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
