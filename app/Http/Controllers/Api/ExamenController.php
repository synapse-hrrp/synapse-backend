<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExamenStoreRequest;
use App\Http\Requests\ExamenUpdateRequest;
use App\Http\Resources\ExamenResource;
use App\Models\Examen;
use App\Models\Personnel;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamenController extends Controller
{
    // GET /examens
    public function index(Request $request)
    {
        $query = Examen::query()->with(['patient','service','demandeur','validateur']);

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->input('patient_id'));
        }

        if ($request->filled('service_slug')) {
            $query->where('service_slug', $request->input('service_slug'));
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->input('statut'));
        }

        if ($request->filled('code')) {
            $query->where('code_examen', 'like', '%'.$request->input('code').'%');
        }

        if ($request->filled('q')) {
            $s = '%'.$request->input('q').'%';
            $query->where(function ($w) use ($s) {
                $w->where('code_examen', 'like', $s)
                  ->orWhere('nom_examen', 'like', $s)
                  ->orWhere('prelevement', 'like', $s);
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date_demande', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date_demande', '<=', $request->input('date_to'));
        }

        $query->orderByDesc('date_demande');

        $perPage   = (int) $request->get('per_page', 15);
        $paginator = $query->paginate($perPage)->appends($request->query());

        return ExamenResource::collection($paginator);
    }

    // POST /examens
    public function store(ExamenStoreRequest $request)
    {
        $data = $request->validated();

        // Renseigner automatiquement le demandeur depuis l'utilisateur connecté (si mappé à un Personnel)
        if (!isset($data['demande_par']) && Auth::check()) {
            $perso = Personnel::where('user_id', Auth::id())->first();
            if ($perso) {
                $data['demande_par'] = $perso->id; // BIGINT ou UUID selon ton schéma
            }
        }

        $examen = Examen::create($data)->load(['patient','service','demandeur','validateur']);

        // ✅ renvoie 201 Created de manière explicite
        return response()->json(
            (new ExamenResource($examen))->toArray($request),
            201
        );
    }

    // GET /examens/{examen}
    public function show(Examen $examen)
    {
        $examen->load(['patient','service','demandeur','validateur']);
        return new ExamenResource($examen);
    }

    // PUT/PATCH /examens/{examen}
    public function update(ExamenUpdateRequest $request, Examen $examen)
    {
        $examen->update($request->validated());
        $examen->load(['patient','service','demandeur','validateur']);
        return new ExamenResource($examen);
    }

    // DELETE /examens/{examen}
    public function destroy(Examen $examen)
    {
        $examen->delete();
        return response()->noContent();
    }

    // POST /services/{service}/examens : créer “depuis un service”
    public function storeForService(ExamenStoreRequest $request, Service $service)
    {
        $data = $request->validated();
        $data['service_slug'] = $service->slug;   // association auto via slug
        $data['type_origine'] = 'interne';

        if (!isset($data['demande_par']) && Auth::check()) {
            $perso = Personnel::where('user_id', Auth::id())->first();
            if ($perso) {
                $data['demande_par'] = $perso->id;
            }
        }

        // Création via la relation (si définie) ou directe
        // $examen = $service->examens()->create($data);
        $examen = Examen::create($data)->load(['patient','service','demandeur','validateur']);

        // ✅ renvoie 201 Created
        return response()->json(
            (new ExamenResource($examen))->toArray($request),
            201
        );
    }
}
