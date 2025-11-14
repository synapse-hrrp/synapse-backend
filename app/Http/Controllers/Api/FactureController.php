<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Facture, FactureLigne, Visite};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class FactureController extends Controller
{
    /**
     * GET /factures
     * Filtres supportés :
     * - numero (LIKE)
     * - q (recherche : numero | patient.nom | patient.prenom | patient.telephone)
     * - patient_id (UUID)
     * - statut (IMPAYEE|PARTIELLE|PAYEE|ANNULEE|…)
     * - devise (size:3)
     * - date_from / date_to (date)
     * - service_id (int)  → filtre via la visite
     * - total_min / total_max (numeric)
     * - due_min / due_max (numeric)
     * - has_due (bool)    → montant_du > 0 si true, = 0 si false
     * - with[] (relations) : lignes|reglements|visite|patient
     * - sort_by (created_at|numero|montant_total|montant_du) + sort_dir (asc|desc)
     * - per_page (1..200), page (>=1)
     *
     * 🔐 IMPORTANT :
     *  - caissier_service  → uniquement factures des services qui lui sont affectés
     *  - caissier_general / admin_caisse / admin → accès global à toutes les factures
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

        // Filtre par service via la Visite (paramètre explicite)
        if (!empty($v['service_id'])) {
            $q->whereHas('visite', fn($vv) => $vv->where('service_id', (int)$v['service_id']));
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
         * 🔐 FILTRAGE PAR RÔLE / SERVICE
         *
         * - admin, admin_caisse, caissier_general  → accès global
         * - caissier_service (et autres)           → limité aux services qui lui sont affectés
         */
        $user = $r->user();

        if ($user) {
            $isGlobalCash = $user->hasAnyRole([
                'admin',
                'admin_caisse',
                'caissier_general',
            ]);

            if (! $isGlobalCash) {
                // On récupère les services du user (pivot user_service) + service principal du personnel
                $user->loadMissing(['services', 'personnel.service']);

                $allowed = [];

                // services via pivot user_service
                foreach ($user->services as $svc) {
                    if ($svc && $svc->id) {
                        $allowed[] = (int) $svc->id;
                    }
                }

                // service principal (personnel.service_id)
                if ($user->personnel?->service_id) {
                    $allowed[] = (int) $user->personnel->service_id;
                }

                if ($user->personnel?->service?->id) {
                    $allowed[] = (int) $user->personnel->service->id;
                }

                $allowed = array_values(array_unique(array_filter($allowed)));

                // 🔍 DEBUG ICI : on inspecte ce que voit le backend pour le caissier
                dd([
                    'user_id'             => $user->id,
                    'user_name'           => $user->name,
                    'roles'               => $user->roles->pluck('name')->values(),
                    'allowed_service_ids' => $allowed,
                ]);

                if (! empty($allowed)) {
                    // On restreint aux factures dont la visite est dans l'un des services autorisés
                    $q->whereHas('visite', function ($vv) use ($allowed) {
                        $vv->whereIn('service_id', $allowed);
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
     * Param optionnel: with[]=lignes&with[]=reglements&with[]=visite&with[]=patient
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
                    'montant_total' => $visite->montant_prevu ?? 0,
                    'montant_du'    => $visite->montant_du ?? ($visite->montant_prevu ?? 0),
                    'devise'        => $visite->devise ?? 'CDF', // garde ton choix initial
                    'statut'        => 'IMPAYEE',
                ]);

                FactureLigne::create([
                    'facture_id'    => $facture->id,
                    'tarif_id'      => $visite->tarif_id,
                    'designation'   => $visite->service->nom ?? 'Consultation',
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
                'montant_total' => 0,
                'montant_du'    => 0,
                'devise'        => $data['devise'] ?? 'XAF', // défaut caisse
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
