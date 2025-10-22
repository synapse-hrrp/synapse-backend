<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeclarationNaissanceRequest;
use App\Http\Requests\UpdateDeclarationNaissanceRequest;
use App\Http\Resources\DeclarationNaissanceResource;
use App\Models\DeclarationNaissance;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class DeclarationNaissanceController extends Controller
{
    public function index(Request $request)
    {
        $q = DeclarationNaissance::query()
            ->with(['mere','service'])
            ->latest();

        if ($request->filled('mere_id'))      $q->where('mere_id', $request->mere_id);
        if ($request->filled('service_slug')) $q->where('service_slug', $request->service_slug);
        if ($request->filled('statut'))       $q->where('statut', $request->statut);
        if ($request->filled('from'))         $q->whereDate('date_heure_naissance', '>=', $request->from);
        if ($request->filled('to'))           $q->whereDate('date_heure_naissance', '<=', $request->to);
        if ($request->filled('search')) {
            $s = trim($request->search);
            $q->where(function ($qq) use ($s) {
                $qq->where('bebe_nom', 'like', "%$s%")
                   ->orWhere('bebe_prenom', 'like', "%$s%")
                   ->orWhere('pere_nom', 'like', "%$s%")
                   ->orWhere('numero_acte', 'like', "%$s%")
                   ->orWhere('lieu_naissance', 'like', "%$s%");
            });
        }

        $perPage = (int) $request->get('per_page', 20);

        return DeclarationNaissanceResource::collection(
            $q->paginate($perPage)->appends($request->query())
        );
    }

    public function show($declaration)
    {
        $model = DeclarationNaissance::with(['mere','service'])->findOrFail($declaration);
        return new DeclarationNaissanceResource($model);
    }

    public function store(StoreDeclarationNaissanceRequest $request)
    {
        Log::info('DeclarationNaissanceController@store', ['payload' => $request->all()]);

        $data = $request->validated();
        $data['created_by_user_id'] = $request->user()?->id;

        $model = DB::transaction(function () use ($data) {
            $m = DeclarationNaissance::create($data);
            app(\App\Services\InvoiceService::class)->attachDeclaration($m);
            return $m->fresh();
        });

        $model->load(['mere','service']);

        $location = Route::has('v1.declarations_naissance.show')
            ? route('v1.declarations_naissance.show', ['declaration' => $model->id])
            : url("/api/v1/declarations-naissance/{$model->id}");

        return (new DeclarationNaissanceResource($model))
            ->additional(['message' => 'Créé'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', $location);
    }

    public function update(UpdateDeclarationNaissanceRequest $request, $declaration)
    {
        $model = DeclarationNaissance::findOrFail($declaration);

        $data = $request->validated();

        DB::transaction(function () use ($model, $data) {
            $model->fill($data)->save();
        });

        $model->load(['mere','service']);

        return (new DeclarationNaissanceResource($model))
            ->additional(['message' => 'Mis à jour']);
    }

    public function destroy($declaration)
    {
        $model = DeclarationNaissance::findOrFail($declaration);
        $model->delete();
        return response()->noContent();
    }

    public function restore($id)
    {
        $model = DeclarationNaissance::withTrashed()->findOrFail($id);
        $model->restore();
        $model->load(['mere','service']);

        return (new DeclarationNaissanceResource($model))
            ->additional(['message' => 'Restauré']);
    }

    public function storeForService(StoreDeclarationNaissanceRequest $request, Service $service)
    {
        Log::info('DeclarationNaissanceController@storeForService', [
            'payload' => $request->all(),
            'service' => $service->slug,
        ]);

        $data = $request->validated();
        $data['service_slug']       = $service->slug;
        $data['created_by_user_id'] = $request->user()?->id;

        $model = DB::transaction(function () use ($data) {
            $m = DeclarationNaissance::create($data);
            app(\App\Services\InvoiceService::class)->attachDeclaration($m);
            return $m->fresh();
        });

        $model->load(['mere','service']);

        $location = Route::has('v1.declarations_naissance.show')
            ? route('v1.declarations_naissance.show', ['declaration' => $model->id])
            : url("/api/v1/declarations-naissance/{$model->id}");

        return (new DeclarationNaissanceResource($model))
            ->additional(['message' => 'Créé (via service)'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', $location);
    }
}
