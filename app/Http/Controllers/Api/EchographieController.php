<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEchographieRequest;
use App\Http\Requests\UpdateEchographieRequest;
use App\Http\Resources\EchographieResource;
use App\Models\Echographie;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EchographieController extends Controller
{
    public function index(Request $request)
    {
        $q = Echographie::query()
            ->with(['patient','service','demandeur','operateur','validateur'])
            ->latest('date_demande');

        if ($request->filled('statut'))       $q->where('statut', $request->statut);
        if ($request->filled('service_slug')) $q->where('service_slug', $request->service_slug);
        if ($request->filled('patient_id'))   $q->where('patient_id', $request->patient_id);

        if ($request->filled('search')) {
            $s = trim($request->search);
            $q->where(function ($qq) use ($s) {
                $qq->where('nom_echo', 'like', "%$s%")
                   ->orWhere('code_echo', 'like', "%$s%")
                   ->orWhere('indication', 'like', "%$s%");
            });
        }

        $perPage = (int) $request->get('per_page', 20); // ← compatible partout

        return EchographieResource::collection(
            $q->paginate($perPage)->appends($request->query())
        );
    }

    public function store(StoreEchographieRequest $request)
    {
        Log::info('EchographieController@store hit', ['payload' => $request->all()]);

        $data = $request->validated();

        // map 'tarif_code' -> 'code_echo' si fourni
        if (!isset($data['code_echo']) && $request->filled('tarif_code')) {
            $data['code_echo'] = strtoupper(trim((string) $request->input('tarif_code')));
        }
        // on ne laisse jamais ces champs venir du client
        unset($data['prix'], $data['devise'], $data['facture_id'], $data['tarif_code']);

        $data['created_by_user_id'] = $request->user()?->id;

        // Retourne le modèle depuis la transaction (pas de référence)
        $model = DB::transaction(function () use ($data) {
            $m = Echographie::create($data); // Observer "creating" valide le tarif
            app(\App\Services\InvoiceService::class)->attachEchographie($m); // crée/attache facture + prix/devise
            return $m->fresh(); // on renvoie une instance fraîche
        });

        $model->load(['patient','service','demandeur','operateur','validateur']);

        return (new EchographieResource($model))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function storeForService(StoreEchographieRequest $request, Service $service)
    {
        Log::info('EchographieController@storeForService hit', [
            'payload' => $request->all(),
            'service' => $service->slug
        ]);

        $data = $request->validated();

        if (!isset($data['code_echo']) && $request->filled('tarif_code')) {
            $data['code_echo'] = strtoupper(trim((string) $request->input('tarif_code')));
        }
        unset($data['prix'], $data['devise'], $data['facture_id'], $data['tarif_code']);

        $data['service_slug']       = $service->slug;
        $data['created_by_user_id'] = $request->user()?->id;

        $model = DB::transaction(function () use ($data) {
            $m = Echographie::create($data);
            app(\App\Services\InvoiceService::class)->attachEchographie($m);
            return $m->fresh();
        });

        $model->load(['patient','service','demandeur','operateur','validateur']);

        return (new EchographieResource($model))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Echographie $echographie)
    {
        $echographie->load(['patient','service','demandeur','operateur','validateur']);
        return new EchographieResource($echographie);
    }

    public function update(UpdateEchographieRequest $request, Echographie $echographie)
    {
        $data = $request->validated();

        if (!isset($data['code_echo']) && $request->filled('tarif_code')) {
            $data['code_echo'] = strtoupper(trim((string) $request->input('tarif_code')));
        }
        unset($data['prix'], $data['devise'], $data['facture_id'], $data['tarif_code']);

        $echographie->fill($data)->save();

        $echographie->load(['patient','service','demandeur','operateur','validateur']);
        return new EchographieResource($echographie);
    }

    public function destroy(Echographie $echographie)
    {
        $echographie->delete();
        return response()->json(['message' => 'Echographie supprimée.']);
    }

    public function restore(string $id)
    {
        $model = Echographie::withTrashed()->findOrFail($id);
        $model->restore();
        $model->load(['patient','service','demandeur','operateur','validateur']);
        return new EchographieResource($model);
    }
}
