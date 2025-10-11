<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Tarif;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class TarifController extends Controller
{
    /**
     * GET /tarifs
     * List with filters, sorting and pagination.
     */
    public function index(Request $request)
    {
        $query = Tarif::query()
            ->when($request->boolean('only_active'), fn ($q) => $q->actifs())
            ->when($request->filled('service'), fn ($q) => $q->forService($request->string('service')))
            ->when($request->filled('code'), fn ($q) => $q->byCode($request->string('code')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = trim((string) $request->string('search'));
                $q->where(function ($qq) use ($s) {
                    $qq->where('code', 'LIKE', "%{$s}%")
                       ->orWhere('libelle', 'LIKE', "%{$s}%");
                });
            });

        // Sorting
        $sort = $request->get('sort', 'created_at');
        $dir  = $request->get('dir', 'desc');
        if (! in_array($sort, ['code', 'libelle', 'montant', 'devise', 'is_active', 'service_slug', 'created_at'])) {
            $sort = 'created_at';
        }
        if (! in_array(strtolower($dir), ['asc', 'desc'])) {
            $dir = 'desc';
        }
        $query->orderBy($sort, $dir);

        // Pagination
        $perPage = (int) $request->get('per_page', 15);
        $perPage = $perPage > 0 && $perPage <= 200 ? $perPage : 15;

        return response()->json(
            $query->paginate($perPage)
        );
    }

    /**
     * POST /tarifs
     * Create a new Tarif
     */
    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $tarif = Tarif::create($data);

        return response()->json($tarif, Response::HTTP_CREATED);
    }

    /**
     * GET /tarifs/{tarif}
     * Show a specific Tarif (route-model bound by id)
     */
    public function show(Tarif $tarif)
    {
        return response()->json($tarif);
    }

    /**
     * PUT/PATCH /tarifs/{tarif}
     * Update a Tarif
     */
    public function update(Request $request, Tarif $tarif)
    {
        $data = $this->validateData($request, $tarif);

        $tarif->fill($data)->save();

        return response()->json($tarif);
    }

    /**
     * DELETE /tarifs/{tarif}
     * Delete (hard delete)
     */
    public function destroy(Tarif $tarif)
    {
        $tarif->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * PATCH /tarifs/{tarif}/toggle
     * Quick toggle of is_active
     */
    public function toggle(Tarif $tarif)
    {
        $tarif->is_active = ! (bool) $tarif->is_active;
        $tarif->save();

        return response()->json($tarif);
    }

    /**
     * GET /tarifs/actifs
     * Convenience endpoint returning only active tarifs
     */
    public function actifs(Request $request)
    {
        $request->merge(['only_active' => true]);
        return $this->index($request);
    }

    /**
     * GET /tarifs/by-code/{code}
     * Fetch by business code
     */
    public function byCode(string $code)
    {
        $tarif = Tarif::query()->byCode($code)->firstOrFail();
        return response()->json($tarif);
    }

    /**
     * Validate and normalize input for store/update
     */
    protected function validateData(Request $request, ?Tarif $current = null): array
    {
        $idToIgnore = $current?->getKey();

        $validated = $request->validate([
            'code'         => [
                'required', 'string', 'max:50',
                Rule::unique('tarifs', 'code')->ignore($idToIgnore, 'id'),
            ],
            'libelle'      => ['required', 'string', 'max:255'],
            'montant'      => ['required', 'numeric', 'min:0'],
            'devise'       => ['nullable', 'string', 'size:3'],
            'is_active'    => ['sometimes', 'boolean'],
            'service_slug' => ['nullable', 'string', 'max:100', Rule::exists('services', 'slug')],
        ]);

        // Optional: force trim on some fields here (model also normalizes)
        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }
        if (isset($validated['devise'])) {
            $validated['devise'] = strtoupper(trim($validated['devise']));
        }

        return $validated;
    }
}
