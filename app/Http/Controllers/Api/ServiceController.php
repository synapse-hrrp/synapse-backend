<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\Personnel; // ✅ import pour optionsForPersonnel
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    // GET /api/services?q=...&active=1&sort=-created_at&per_page=20
    public function index(Request $request)
    {
        $query = Service::query();

        // recherche texte simple (name, code, slug)
        if ($q = $request->string('q')->toString()) {
            $query->where(function ($qbuilder) use ($q) {
                $qbuilder->where('name', 'like', "%$q%")
                         ->orWhere('code', 'like', "%$q%")
                         ->orWhere('slug', 'like', "%$q%");
            });
        }

        // filtre actif/inactif
        if (!is_null($request->query('active'))) {
            $active = filter_var($request->query('active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if (!is_null($active) && \Schema::hasColumn('services', 'is_active')) {
                $query->where('is_active', $active);
            }
        }

        // tri (ex: sort=name ou sort=-created_at)
        $sort = $request->query('sort', 'name'); // par défaut: alphabétique
        foreach (explode(',', $sort) as $part) {
            $dir = \Illuminate\Support\Str::startsWith($part, '-') ? 'desc' : 'asc';
            $col = ltrim($part, '-');
            if (in_array($col, ['name','code','slug','created_at','updated_at','is_active'])) {
                $query->orderBy($col, $dir);
            }
        }

        // ➜ MODE OPTIONS: renvoyer un array plat [{id,name}]
        if ($request->query('mode') === 'options') {
            $limit = (int) $request->query('limit', 1000);
            $items = $query->select('id','name')->limit($limit)->get();
            return response()->json($items);
        }

        // ➜ Mode normal: Resource + pagination
        $perPage = (int) $request->query('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        return ServiceResource::collection(
            $query->paginate($perPage)->appends($request->query())
        );
    }

    // POST /api/services
    public function store(ServiceRequest $request)
    {
        $data = $request->validated();

        // slug auto si absent
        if (empty($data['slug'])) {
            $base = Str::slug($data['name']);
            $slug = $base;
            $i = 1;
            while (Service::where('slug', $slug)->exists()) {
                $slug = $base.'-'.$i++;
            }
            $data['slug'] = $slug;
        }

        $service = Service::create($data);

        return (new ServiceResource($service))
            ->response()
            ->setStatusCode(201);
    }

    // GET /api/services/{service:slug}
    public function show(Service $service)
    {
        return new ServiceResource($service);
    }

    // PUT/PATCH /api/services/{service:slug}
    public function update(ServiceRequest $request, Service $service)
    {
        $data = $request->validated();

        // si slug absent mais name change → régénérer slug optionnel (à désactiver si tu veux garder le slug fixe)
        if (!isset($data['slug']) && isset($data['name'])) {
            // ne pas toucher au slug existant par défaut
            // dé-commente pour régénérer automatiquement :
            // $data['slug'] = $this->uniqueSlugFromName($data['name'], $service->id);
        }

        $service->update($data);

        return new ServiceResource($service);
    }

    // DELETE /api/services/{service:slug}
    public function destroy(Service $service)
    {
        $service->delete();

        return response()->json(['message' => 'Service deleted'], 200);
    }

    // helper si tu veux régénérer le slug à l’update
    protected function uniqueSlugFromName(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        $exists = fn($s) => Service::where('slug', $s)
            ->when($ignoreId, fn($q) => $q->where('id','!=',$ignoreId))
            ->exists();
        while ($exists($slug)) {
            $slug = $base.'-'.$i++;
        }
        return $slug;
    }

    // ✅ GET /api/services/options-for-personnel/{personnel}
    // Renvoie: { options: [{id,name},...], selected: {id,name}|null }
    public function optionsForPersonnel(Request $request, int $personnelId)
    {
        // 1) Lire le filtre d’activité : active=1|0|all (par défaut: all / pas de filtre)
        $activeParam = $request->query('active', 'all'); // '1', '0' ou 'all'

        // 2) Construire la query des options
        $selectCols = \Schema::hasColumn('services', 'is_active')
            ? ['id','name','is_active']
            : ['id','name'];

        $q = Service::query()->select($selectCols)->orderBy('name', 'asc');

        if (\Schema::hasColumn('services', 'is_active')) {
            if ($activeParam === '1' || $activeParam === 1) {
                $q->where('is_active', true);
            } elseif ($activeParam === '0' || $activeParam === 0) {
                $q->where('is_active', false);
            }
            // 'all' => pas de filtre
        }

        $options = $q->get();

        // 3) Récupérer le service actuel du personnel
        $selected = null;
        $selectedId = \App\Models\Personnel::query()->where('id', $personnelId)->value('service_id');

        if ($selectedId) {
            // Charger le service sélectionné (même s’il est filtré hors options)
            $selectedCols = $selectCols;
            $selectedRow = Service::query()->select($selectedCols)->find($selectedId);

            if ($selectedRow) {
                // Est-ce que la valeur sélectionnée est présente dans la liste options ?
                $inOptions = $options->contains('id', $selectedRow->id);

                // Construire l’objet selected enrichi
                $selected = [
                    'id'         => $selectedRow->id,
                    'name'       => $selectedRow->name,
                ];

                if (in_array('is_active', $selectCols, true)) {
                    $selected['is_active'] = (bool) $selectedRow->is_active;
                }

                $selected['in_options'] = $inOptions;
            }
        }

        return response()->json([
            'options'  => $options,
            'selected' => $selected, // peut être null si pas de service affecté
        ]);
    }

}
