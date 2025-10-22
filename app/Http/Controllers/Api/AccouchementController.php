<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccouchementRequest;
use App\Http\Requests\UpdateAccouchementRequest;
use App\Http\Resources\AccouchementResource;
use App\Models\Accouchement;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AccouchementController extends Controller
{
    public function index(Request $request)
    {
        $q = Accouchement::query()
            ->with(['mere','service','sageFemme','obstetricien'])
            ->latest('date_heure_accouchement');

        if ($request->filled('mere_id'))      $q->where('mere_id', $request->mere_id);
        if ($request->filled('service_slug')) $q->where('service_slug', $request->service_slug);
        if ($request->filled('statut'))       $q->where('statut', $request->statut);
        if ($request->filled('from'))         $q->whereDate('date_heure_accouchement', '>=', $request->from);
        if ($request->filled('to'))           $q->whereDate('date_heure_accouchement', '<=', $request->to);

        // si ton projet est < Laravel 9, remplace integer() par (int) $request->get('per_page', 20)
        $perPage = method_exists($request, 'integer')
            ? $request->integer('per_page', 20)
            : (int) $request->get('per_page', 20);

        return AccouchementResource::collection(
            $q->paginate($perPage)->appends($request->query())
        );
    }

    public function show(Accouchement $accouchement)
    {
        $accouchement->load(['mere','service','sageFemme','obstetricien']);
        return new AccouchementResource($accouchement);
    }

    public function store(StoreAccouchementRequest $request)
    {
        $data = $request->validated();
        $data['created_by_user_id'] = $request->user()?->id;

        $model = DB::transaction(function () use ($data) {
            $m = Accouchement::create($data);
            app(\App\Services\InvoiceService::class)->attachAccouchement($m);
            return $m->fresh(); // on renvoie le modèle créé
        });

        $model->load(['mere','service','sageFemme','obstetricien']);

        return (new AccouchementResource($model))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('v1.accouchements.show', $model->id));
    }

    public function update(UpdateAccouchementRequest $request, Accouchement $accouchement)
    {
        $accouchement->fill($request->validated())->save();
        $accouchement->load(['mere','service','sageFemme','obstetricien']);
        return new AccouchementResource($accouchement); // 200
    }

    public function destroy(Accouchement $accouchement)
    {
        $accouchement->delete();
        return response()->noContent(); // 204
    }

    public function restore(string $id)
    {
        $model = Accouchement::withTrashed()->findOrFail($id);
        $model->restore();
        $model->load(['mere','service','sageFemme','obstetricien']);
        return new AccouchementResource($model); // 200
    }

    public function storeForService(StoreAccouchementRequest $request, Service $service)
    {
        $data = $request->validated();
        $data['service_slug']       = $service->slug;
        $data['created_by_user_id'] = $request->user()?->id;

        $model = DB::transaction(function () use ($data) {
            $m = Accouchement::create($data);
            app(\App\Services\InvoiceService::class)->attachAccouchement($m);
            return $m->fresh();
        });

        $model->load(['mere','service','sageFemme','obstetricien']);

        return (new AccouchementResource($model))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('v1.accouchements.show', $model->id));
    }
}
