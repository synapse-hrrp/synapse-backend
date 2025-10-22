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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamenController extends Controller
{
    /**
     * GET /examens
     * Liste paginée des examens, avec filtres optionnels.
     */
    public function index(Request $request)
    {
        $query = Examen::query()->with(['patient','service','demandeur','validateur']);

        // 🔍 Filtres dynamiques
        if ($request->filled('service_slug')) {
            $query->where('service_slug', $request->string('service_slug'));
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }

        if ($request->filled('demande_par')) {
            $query->where('demande_par', $request->integer('demande_par'));
        }

        // Intervalle de dates sur la date de demande
        if ($request->filled('date_min')) {
            $query->whereDate('date_demande', '>=', $request->date('date_min'));
        }
        if ($request->filled('date_max')) {
            $query->whereDate('date_demande', '<=', $request->date('date_max'));
        }

        // Recherche plein-texte simple sur nom/code
        if ($request->filled('search')) {
            $search = trim($request->string('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nom_examen', 'LIKE', "%{$search}%")
                  ->orWhere('code_examen', 'LIKE', "%{$search}%");
            });
        }

        // Tri (par défaut: plus récents d’abord)
        $sort = $request->string('sort', '-date_demande'); // ex: -date_demande, date_demande, nom_examen
        if (str_starts_with($sort, '-')) {
            $query->orderBy(ltrim($sort, '-'), 'desc');
        } else {
            $query->orderBy($sort, 'asc');
        }

        // Pagination
        $perPage = (int) $request->get('per_page', 20);
        $examens = $query->paginate($perPage);

        return ExamenResource::collection($examens);
    }

    /**
     * POST /examens
     * Création GÉNÉRIQUE : si service_slug est fourni -> création "depuis un service"
     * sinon -> création "depuis le labo".
     */
    public function store(ExamenStoreRequest $request)
    {
        $data = $request->validated();

        // Demandeur auto (si mappé)
        if (!isset($data['demande_par']) && Auth::check()) {
            if ($perso = Personnel::where('user_id', Auth::id())->first()) {
                $data['demande_par'] = $perso->id;
            }
        }

        // Déterminer l’origine selon la présence de service_slug dans le payload
        $hasService                 = !empty($data['service_slug']);
        $data['created_via']        = $hasService ? 'service' : 'labo';
        $data['type_origine']       = $hasService ? 'interne' : 'externe';
        $data['created_by_user_id'] = Auth::id();
        $data['date_demande']       = $data['date_demande'] ?? now();

        // Normaliser le code examen si fourni
        if (!empty($data['code_examen'])) {
            $data['code_examen'] = strtoupper(trim($data['code_examen']));
        }

        // Résoudre la tarification UNIQUEMENT dans les services Labo
        if ($tarif = $this->resolveLabTarif($data)) {
            // Prix/devise viennent du tarif si un tarif est fourni
            $data['prix']        = $tarif->montant;
            $data['devise']      = $tarif->devise ?? 'XAF';

            // Compléter code/nom seulement s'ils sont vides
            $data['code_examen'] = $data['code_examen'] ?? $tarif->code;
            $data['nom_examen']  = $data['nom_examen']  ?? ($tarif->libelle ?? $tarif->code);
        }

        // Nettoyage des champs de pilotage tarification
        unset($data['tarif_id'], $data['tarif_code']);

        $examen = Examen::create($data)->load(['patient','service','demandeur','validateur']);

        return response()->json((new ExamenResource($examen))->toArray($request), 201);
    }

    /**
     * GET /examens/{examen}
     */
    public function show(Examen $examen)
    {
        $examen->load(['patient','service','demandeur','validateur']);
        return new ExamenResource($examen);
    }

    /**
     * PUT/PATCH /examens/{examen}
     */
    public function update(ExamenUpdateRequest $request, Examen $examen)
    {
        $data = $request->validated();

        // Normaliser le code examen si fourni
        if (!empty($data['code_examen'])) {
            $data['code_examen'] = strtoupper(trim($data['code_examen']));
        }

        // Si une info de tarification est fournie, recalculer depuis le Labo
        if (!empty($data['tarif_id']) || !empty($data['tarif_code'])) {
            if ($tarif = $this->resolveLabTarif(array_merge($examen->toArray(), $data))) {
                $data['prix']        = $tarif->montant;
                $data['devise']      = $tarif->devise ?? 'XAF';
                $data['code_examen'] = $data['code_examen'] ?? $tarif->code;
                $data['nom_examen']  = $data['nom_examen']  ?? ($tarif->libelle ?? $tarif->code);
            }
        }

        // Nettoyage des champs de pilotage tarification
        unset($data['tarif_id'], $data['tarif_code']);

        $examen->update($data);
        $examen->load(['patient','service','demandeur','validateur']);

        return new ExamenResource($examen);
    }

    /**
     * DELETE /examens/{examen}
     */
    public function destroy(Examen $examen)
    {
        $examen->delete();
        return response()->noContent(); // 204
    }

    /**
     * POST /services/{service}/examens (création DEPUIS UN SERVICE)
     * NB : La tarification reste basée sur les slugs Labo, PAS sur $service->slug.
     */
    public function storeForService(ExamenStoreRequest $request, Service $service)
    {
        $data = $request->validated();

        // Traçabilité Service
        $data['service_slug']       = $service->slug;
        $data['type_origine']       = 'interne';
        $data['created_via']        = 'service';
        $data['created_by_user_id'] = Auth::id();
        $data['date_demande']       = $data['date_demande'] ?? now();

        // Demandeur auto si besoin
        if (!isset($data['demande_par']) && Auth::check()) {
            if ($perso = Personnel::where('user_id', Auth::id())->first()) {
                $data['demande_par'] = $perso->id;
            }
        }

        if (!empty($data['code_examen'])) {
            $data['code_examen'] = strtoupper(trim($data['code_examen']));
        }

        // Tarification UNIQUEMENT via services Labo
        if ($tarif = $this->resolveLabTarif($data)) {
            $data['prix']        = $tarif->montant;
            $data['devise']      = $tarif->devise ?? 'XAF';
            $data['code_examen'] = $data['code_examen'] ?? $tarif->code;
            $data['nom_examen']  = $data['nom_examen']  ?? ($tarif->libelle ?? $tarif->code);
        }

        unset($data['tarif_id'], $data['tarif_code']);

        $examen = Examen::create($data)->load(['patient','service','demandeur','validateur']);

        return response()->json((new ExamenResource($examen))->toArray($request), 201);
    }

    /**
     * Résout un tarif UNIQUEMENT dans les services de laboratoire configurés.
     * Priorité au tarif_id, sinon tarif_code (actif, le plus récent).
     */
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
