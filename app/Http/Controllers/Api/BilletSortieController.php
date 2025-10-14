<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBilletSortieRequest;
use App\Http\Requests\UpdateBilletSortieRequest;
use App\Http\Resources\BilletSortieResource;
use App\Models\BilletSortie;
use App\Models\Service;
use Illuminate\Http\Request;

class BilletSortieController extends Controller
{
    public function index(Request $request)
    {
        $q = BilletSortie::query()
            ->with(['patient','service','signataire'])
            ->latest();

        if ($request->filled('patient_id'))   $q->where('patient_id', $request->patient_id);
        if ($request->filled('service_slug')) $q->where('service_slug', $request->service_slug);
        if ($request->filled('statut'))       $q->where('statut', $request->statut);
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function($qq) use ($s) {
                $qq->where('diagnostic_sortie', 'like', "%$s%")
                   ->orWhere('resume_clinique', 'like', "%$s%")
                   ->orWhere('consignes', 'like', "%$s%");
            });
        }

        return BilletSortieResource::collection(
            $q->paginate($request->get('per_page', 20))->appends($request->query())
        );
    }

    public function show(BilletSortie $billet)
    {
        $billet->load(['patient','service','signataire']);
        return new BilletSortieResource($billet);
    }

    public function store(StoreBilletSortieRequest $request)
    {
        $data = $request->validated();
        $data['created_by_user_id'] = $request->user()?->id;

        $billet = BilletSortie::create($data);
        $billet->load(['patient','service','signataire']);

        return (new BilletSortieResource($billet))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateBilletSortieRequest $request, BilletSortie $billet)
    {
        $billet->fill($request->validated())->save();
        $billet->load(['patient','service','signataire']);
        return new BilletSortieResource($billet);
    }

    public function destroy(BilletSortie $billet)
    {
        $billet->delete();
        return response()->json(['message' => 'Billet de sortie supprimé.']);
    }

    public function restore($id)
    {
        $billet = BilletSortie::withTrashed()->findOrFail($id);
        $billet->restore();
        $billet->load(['patient','service','signataire']);
        return new BilletSortieResource($billet);
    }

    // Création depuis un service (comme pour examens/échographies)
    public function storeForService(StoreBilletSortieRequest $request, Service $service)
    {
        $data = $request->validated();
        $data['service_slug']       = $service->slug;
        $data['created_by_user_id'] = $request->user()?->id;

        $billet = BilletSortie::create($data);
        $billet->load(['patient','service','signataire']);

        return (new BilletSortieResource($billet))
            ->response()
            ->setStatusCode(201);
    }
}
