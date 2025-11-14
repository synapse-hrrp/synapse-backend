<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExamenStoreRequest;
use App\Http\Requests\ExamenUpdateRequest;
use App\Http\Resources\ExamenResource;
use App\Models\Examen;
use App\Models\Personnel;
use App\Models\Service;
use App\Models\Tarif;
use App\Models\Medecin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamenController extends Controller
{
    public function index(Request $request)
    {
        $query = Examen::query()->with([
            'patient',
            'service',
            'demandeur.personnel',   // <- important pour nom/prénom
            'validateur',            // Personnel
            'facture',
        ]);

        if ($request->filled('service_slug')) {
            $query->where('service_slug', $request->string('service_slug'));
        }
        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->string('patient_id'));
        }
        if ($request->filled('demande_par')) {
            $query->where('demande_par', (int) $request->input('demande_par'));
        }
        if ($request->filled('date_min')) {
            $query->whereDate('date_demande', '>=', $request->date('date_min'));
        }
        if ($request->filled('date_max')) {
            $query->whereDate('date_demande', '<=', $request->date('date_max'));
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nom_examen', 'LIKE', "%{$search}%")
                  ->orWhere('code_examen', 'LIKE', "%{$search}%");
            });
        }

        $sort = (string) $request->input('sort', '-date_demande');
        $query->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc');

        return ExamenResource::collection(
            $query->paginate((int) $request->get('per_page', 20))
        );
    }

    public function store(ExamenStoreRequest $request)
    {
        $data = $request->validated();

        // Uppercase du code si fourni
        if (!empty($data['code_examen'])) {
            $data['code_examen'] = strtoupper(trim($data['code_examen']));
        }

        // Auto "demandé_par" à partir de l'utilisateur connecté si manquant
        if (!isset($data['demande_par']) && Auth::check()) {
            if ($perso = Personnel::where('user_id', Auth::id())->first()) {
                if ($doc = Medecin::where('personnel_id', $perso->id)->first()) {
                    $data['demande_par'] = $doc->id;
                }
            }
        }

        // Créateur
        $data['created_by_user_id'] = Auth::id();

        // Si un tarif est fourni, on supprime prix/devise pour éviter un conflit
        // et on pousse code_examen si absent, pour que le modèle résolve le tarif.
        if (!empty($data['tarif_id']) || !empty($data['tarif_code'])) {
            if (empty($data['code_examen'])) {
                if ($tarif = $this->resolveLabTarif($data)) {
                    $data['code_examen'] = $tarif->code;
                }
            }
            unset($data['prix'], $data['devise']);
        }

        // On laisse le modèle gérer :
        // - date_demande (auto)
        // - type_origine / created_via
        // - prescripteur_externe (vidé si interne)
        // - prix/devise/nom via Tarifs
        unset($data['type_origine'], $data['created_via'], $data['date_demande'], $data['date_validation']);
        unset($data['tarif_id'], $data['tarif_code']);

        $examen = Examen::create($data);
        $examen->load(['patient','service','demandeur.personnel','validateur','facture']);

        return response()->json(new ExamenResource($examen), 201);
    }

    public function show(Examen $examen)
    {
        $examen->load(['patient','service','demandeur.personnel','validateur','facture']);
        return new ExamenResource($examen);
    }

    public function update(ExamenUpdateRequest $request, Examen $examen)
    {
        $data = $request->validated();

        if (!empty($data['code_examen'])) {
            $data['code_examen'] = strtoupper(trim($data['code_examen']));
        }

        // ⚠️ Ne PAS gérer ici les tarifs/prix/devise (prohibited en UpdateRequest).
        // Le modèle réajustera prix/nom/devise si code_examen/service_slug a changé.

        $examen->update($data);
        $examen->load(['patient','service','demandeur.personnel','validateur','facture']);

        return new ExamenResource($examen);
    }

    public function destroy(Examen $examen)
    {
        $examen->delete();
        return response()->noContent();
    }

    private function resolveLabTarif(array $data): ?Tarif
    {
        $labSlugs = config('billing.lab_service_slugs', ['laboratoire','labo','examens']);

        if (!empty($data['tarif_id'])) {
            return Tarif::query()
                ->whereKey($data['tarif_id'])
                ->whereIn('service_slug', $labSlugs)
                ->first();
        }

        if (!empty($data['tarif_code'])) {
            return Tarif::query()
                ->actifs()
                ->byCode(strtoupper(trim($data['tarif_code'])))
                ->whereIn('service_slug', $labSlugs)
                ->latest('created_at')
                ->first();
        }

        return null;
    }
}
