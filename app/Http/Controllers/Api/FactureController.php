<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Facture, FactureLigne, Visite, Service};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use App\Support\ServiceAccess; // 👈 IMPORTANT

class FactureController extends Controller
{
    /**
     * GET /factures
     */
    public function index(Request $r): JsonResponse
    {
        $v = $r->validate([
            'numero'     => ['nullable','string','max:100'],
            'q'          => ['nullable','string','max:100'],
            'patient_id' => ['nullable','uuid'],
            'statut'     => ['nullable','string','max:30'],
            'devise'     => ['nullable','string','size:3'],

            'date_from'  => ['nullable','date'],
            'date_to'    => ['nullable','date'],

            'service_id' => ['nullable','integer'],

            'total_min'  => ['nullable','numeric'],
            'total_max'  => ['nullable','numeric'],
            'due_min'    => ['nullable','numeric'],
            'due_max'    => ['nullable','numeric'],
            'has_due'    => ['nullable','boolean'],

            'with'       => ['nullable','array'],
            'with.*'     => ['nullable', Rule::in(['lignes','reglements','visite','patient'])],

            'sort_by'    => ['nullable', Rule::in(['created_at','numero','montant_total','montant_du'])],
            'sort_dir'   => ['nullable', Rule::in(['asc','desc'])],

            'per_page'   => ['nullable','integer','min:1','max:200'],
            'page'       => ['nullable','integer','min:1'],
        ]);

        $perPage = (int)($v['per_page'] ?? 20);
        $sortBy  = $v['sort_by']  ?? 'created_at';
        $sortDir = $v['sort_dir'] ?? 'desc';

        // Prépare eager-loading demandé
        $with = array_values(array_unique($v['with'] ?? []));
        $q = Facture::query()->with($with);

        // Filtres simples
        if (!empty($v['numero'])) {
            $q->where('numero', 'like', '%'.$v['numero'].'%');
        }
        if (!empty($v['patient_id'])) {
            $q->where('patient_id', $v['patient_id']);
        }
        if (!empty($v['statut'])) {
            $q->where('statut', $v['statut']);
        }
        if (!empty($v['devise'])) {
            $q->where('devise', strtoupper($v['devise']));
        }

        // Date range (sur created_at)
        if (!empty($v['date_from'])) {
            $q->whereDate('created_at', '>=', $v['date_from']);
        }
        if (!empty($v['date_to'])) {
            $q->whereDate('created_at', '<=', $v['date_to']);
        }

        /**
         * 🔎 Filtre par service explicite (paramètre service_id)
         * On tient compte :
         *  - factures.service_id (colonne directe)
         *  - visites.service_id
         *  - examens.service_slug
         */
        if (!empty($v['service_id'])) {
            $serviceId = (int) $v['service_id'];

            // récupérer le slug du service pour matcher les examens
            $slug = Service::where('id', $serviceId)->value('slug');

            $q->where(function ($sub) use ($serviceId, $slug) {
                // 1) via factures.service_id
                $sub->where('service_id', $serviceId);

                // 2) via visite.service_id
                $sub->orWhereHas('visite', function ($vv) use ($serviceId) {
                    $vv->where('service_id', $serviceId);
                });

                // 3) via examens.service_slug
                if ($slug) {
                    $sub->orWhereHas('examens', function ($ve) use ($slug) {
                        $ve->where('service_slug', $slug);
                    });
                }
            });
        }

        // Montants (total et dû)
        if (isset($v['total_min'])) $q->where('montant_total', '>=', (float)$v['total_min']);
        if (isset($v['total_max'])) $q->where('montant_total', '<=', (float)$v['total_max']);
        if (isset($v['due_min']))   $q->where('montant_du',    '>=', (float)$v['due_min']);
        if (isset($v['due_max']))   $q->where('montant_du',    '<=', (float)$v['due_max']);

        // has_due : true => > 0 ; false => = 0
        if (array_key_exists('has_due', $v)) {
            $v['has_due']
                ? $q->where('montant_du', '>', 0)
                : $q->where('montant_du', '=', 0);
        }

        // 🔎 Recherche plein-texte q : numero | patient.nom | patient.prenom | patient.telephone
        if (!empty($v['q'])) {
            $s = trim($v['q']);
            $q->where(function ($qq) use ($s) {
                $qq->where('numero', 'like', "%{$s}%")
                   ->orWhereHas('patient', function ($qp) use ($s) {
                       $qp->where('nom', 'like', "%{$s}%")
                          ->orWhere('prenom', 'like', "%{$s}%")
                          ->orWhere('telephone', 'like', "%{$s}%");
                   });
            });
        }

        /**
         * 🔐 FILTRAGE PAR RÔLE / SERVICE (via ServiceAccess)
         *
         * - admin, admin_caisse, caissier_general → accès global
         * - caissier_service (et autres)          → limité aux services autorisés
         *
         * On tient compte des factures :
         *   - factures.service_id
         *   - visites.service_id
         *   - examens.service_slug
         */
        if ($user = $r->user()) {
            /** @var ServiceAccess $access */
            $access = app(ServiceAccess::class);

            if (! $access->isGlobal($user)) {
                $allowedIds = $access->allowedServiceIds($user);
                $allowedIds = array_values(array_unique(array_map('intval', $allowedIds)));

                if (! empty($allowedIds)) {
                    // On récupère les slugs correspondant aux services autorisés
                    $allowedSlugs = Service::whereIn('id', $allowedIds)->pluck('slug')->all();

                    $q->where(function ($sub) use ($allowedIds, $allowedSlugs) {
                        // 1) factures qui ont directement service_id
                        $sub->whereIn('service_id', $allowedIds);

                        // 2) factures liées à une visite (consultations, etc.)
                        $sub->orWhereHas('visite', function ($vv) use ($allowedIds) {
                            $vv->whereIn('service_id', $allowedIds);
                        });

                        // 3) factures d'examens (labo, imagerie, etc.)
                        if (!empty($allowedSlugs)) {
                            $sub->orWhereHas('examens', function ($ve) use ($allowedSlugs) {
                                $ve->whereIn('service_slug', $allowedSlugs);
                            });
                        }
                    });
                } else {
                    // Aucun service autorisé => aucune facture visible
                    $q->whereRaw('1 = 0');
                }
            }
        }

        // Tri contrôlé (par défaut created_at desc)
        $q->orderBy($sortBy, $sortDir);

        $page = $q->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'per_page'     => $page->perPage(),
                    'total'        => $page->total(),
                    'last_page'    => $page->lastPage(),
                ],
                'sort' => [
                    'by'  => $sortBy,
                    'dir' => $sortDir,
                ],
                'filters' => [
                    'numero'     => $v['numero']    ?? null,
                    'q'          => $v['q']         ?? null,
                    'patient_id' => $v['patient_id']?? null,
                    'statut'     => $v['statut']    ?? null,
                    'devise'     => $v['devise']    ?? null,
                    'date_from'  => $v['date_from'] ?? null,
                    'date_to'    => $v['date_to']   ?? null,
                    'service_id' => $v['service_id']?? null,
                    'total_min'  => $v['total_min'] ?? null,
                    'total_max'  => $v['total_max'] ?? null,
                    'due_min'    => $v['due_min']   ?? null,
                    'due_max'    => $v['due_max']   ?? null,
                    'has_due'    => array_key_exists('has_due', $v) ? (bool)$v['has_due'] : null,
                ],
            ],
        ], 200);
    }

    /**
     * GET /factures/{id}
     */
    public function show(Request $r, string $id): JsonResponse
    {
        $r->validate([
            'with'   => ['nullable','array'],
            'with.*' => ['nullable', Rule::in(['lignes','reglements','visite','patient'])],
        ]);
        $with = array_values(array_unique($r->input('with', ['lignes','reglements','visite'])));

        $facture = Facture::with($with)->findOrFail($id);

        return response()->json(
            [
                'data'  => $facture,
                'links' => [
                    'self' => route('factures.show', $facture),
                    'pdf'  => route('factures.pdf',  $facture),
                ],
            ],
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    /**
     * POST /factures
     * Flux A: { visite_id }
     * Flux B: { patient_id?, devise? }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'visite_id'  => ['nullable','uuid','exists:visites,id'],
            'patient_id' => ['nullable','uuid','exists:patients,id'],
            'devise'     => ['nullable','string','size:3'],
        ]);

        if (! $request->filled('visite_id') && ! $request->filled('patient_id') && ! $request->filled('devise')) {
            throw ValidationException::withMessages([
                'visite_id' => 'Fournir "visite_id" OU les champs d’une facture libre ("patient_id" et/ou "devise").',
            ]);
        }

        $facture = DB::transaction(function () use ($data) {
            // Flux A — depuis visite
            if (!empty($data['visite_id'])) {
                /** @var Visite $visite */
                $visite = Visite::with(['service','tarif'])->findOrFail($data['visite_id']);

                $facture = Facture::create([
                    'visite_id'     => $visite->id,
                    'patient_id'    => $visite->patient_id,
                    'service_id'    => $visite->service_id, // 👈 on copie aussi ici si tu veux
                    'montant_total' => $visite->montant_prevu ?? 0,
                    'montant_du'    => $visite->montant_du ?? ($visite->montant_prevu ?? 0),
                    'devise'        => $visite->devise ?? 'CDF',
                    'statut'        => 'IMPAYEE',
                ]);

                FactureLigne::create([
                    'facture_id'    => $facture->id,
                    'tarif_id'      => $visite->tarif_id,
                    'designation'   => $visite->service->name ?? $visite->service->nom ?? 'Consultation',
                    'quantite'      => 1,
                    'prix_unitaire' => $visite->montant_prevu ?? 0,
                    'montant'       => $visite->montant_prevu ?? 0,
                ]);

                $visite->update(['statut' => 'A_ENCAISSER']);

                $facture->recalc();

                return $facture->fresh(['lignes','visite']);
            }

            // Flux B — facture libre (caisse)
            $facture = Facture::create([
                'visite_id'     => null,
                'patient_id'    => $data['patient_id'] ?? null,
                'service_id'    => null,
                'montant_total' => 0,
                'montant_du'    => 0,
                'devise'        => $data['devise'] ?? 'XAF',
                'statut'        => 'IMPAYEE',
            ]);

            $facture->recalc();

            return $facture->fresh(['lignes']);
        });

        return response()->json([
            'data' => $facture,
            'links' => [
                'self' => route('factures.show', $facture),
                'pdf'  => route('factures.pdf',  $facture),
            ]
        ], 201);
    }

    /**
     * GET /factures/{facture}/pdf
     */
    public function pdf(Facture $facture): Response
    {
        $facture->load([
            'lignes',
            'reglements',
            'visite.patient',
            'patient',
        ]);

        $patient = $facture->patient ?? ($facture->visite->patient ?? null);

        $pdf = Pdf::loadView('factures.pdf', [
            'facture' => $facture,
            'patient' => $patient,
        ]);

        return $pdf->stream($facture->numero . '.pdf');
    }
}
