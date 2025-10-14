<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeclarationNaissanceRequest;
use App\Http\Requests\UpdateDeclarationNaissanceRequest;
use App\Http\Resources\DeclarationNaissanceResource;
use App\Models\DeclarationNaissance;
use App\Models\Service;
use Illuminate\Http\Request;

class DeclarationNaissanceController extends Controller
{
    public function index(Request $request)
    {
        $q = DeclarationNaissance::query()
            ->with(['patient','mere','pere','service'])
            ->latest();

        if ($request->filled('patient_id'))   $q->where('patient_id', $request->patient_id);
        if ($request->filled('mere_id'))      $q->where('mere_id', $request->mere_id);
        if ($request->filled('service_slug')) $q->where('service_slug', $request->service_slug);
        if ($request->filled('statut'))       $q->where('statut', $request->statut);
        if ($request->filled('from'))         $q->whereDate('date_heure_naissance', '>=', $request->from);
        if ($request->filled('to'))           $q->whereDate('date_heure_naissance', '<=', $request->to);
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function($qq) use ($s) {
                $qq->where('numero_acte', 'like', "%$s%")
                   ->orWhere('lieu_naissance', 'like', "%$s%");
            });
        }

        return DeclarationNaissanceResource::collection(
            $q->paginate($request->get('per_page', 20))->appends($request->query())
        );
    }

    public function show(DeclarationNaissance $declaration)
    {
        $declaration->load(['patient','mere','pere','service']);
        return new DeclarationNaissanceResource($declaration);
    }

    public function store(StoreDeclarationNaissanceRequest $request)
    {
        $data = $request->validated();
        $data['created_by_user_id'] = $request->user()?->id;

        $declaration = DeclarationNaissance::create($data);
        $declaration->load(['patient','mere','pere','service']);

        return (new DeclarationNaissanceResource($declaration))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateDeclarationNaissanceRequest $request, DeclarationNaissance $declaration)
    {
        $declaration->fill($request->validated())->save();
        $declaration->load(['patient','mere','pere','service']);
        return new DeclarationNaissanceResource($declaration);
    }

    public function destroy(DeclarationNaissance $declaration)
    {
        $declaration->delete();
        return response()->json(['message' => 'Déclaration de naissance supprimée.']);
    }

    public function restore($id)
    {
        $declaration = DeclarationNaissance::withTrashed()->findOrFail($id);
        $declaration->restore();
        $declaration->load(['patient','mere','pere','service']);
        return new DeclarationNaissanceResource($declaration);
    }

    // création “depuis un service”
    public function storeForService(StoreDeclarationNaissanceRequest $request, Service $service)
    {
        $data = $request->validated();
        $data['service_slug']       = $service->slug;
        $data['created_by_user_id'] = $request->user()?->id;

        $declaration = DeclarationNaissance::create($data);
        $declaration->load(['patient','mere','pere','service']);

        return (new DeclarationNaissanceResource($declaration))
            ->response()
            ->setStatusCode(201);
    }
}
