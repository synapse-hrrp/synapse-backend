<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Controller;
use App\Models\Pharmacie\PharmaArticle;
use App\Models\Pharmacie\Dci; // ← AJOUT
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    
    // GET /pharma/articles?q=&active=1&per_page=&dci_id= (ou dci_id[]=)
    public function index(Request $request)
    {
        $q      = trim((string) ($request->query('q') ?? $request->query('search', '')));
        $form   = trim((string) ($request->query('form') ?? $request->query('forme', '')));
        $active = $request->query('active');
        $per    = max(1, min((int) $request->query('per_page', 20), 100));

        $query = PharmaArticle::query()
            ->with('dci:id,name')
            ->withSum('lots as stock_on_hand', 'quantity');

        if ($q !== '')    $query->search($q);
        if ($form !== '') $query->where('form', $form);

        if (!is_null($active)) {
            $activeBool = filter_var($active, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if (!is_null($activeBool)) {
                $query->where('is_active', $activeBool);
            }
        }

        // ← AJOUT : filtre DCI (int ou tableau)
        $dciParam = $request->query('dci_id');
        if (!is_null($dciParam) && $dciParam !== '') {
            if (is_array($dciParam)) {
                $ids = array_values(array_filter(array_map('intval', $dciParam), fn($v) => $v > 0));
                if ($ids) $query->whereIn('dci_id', $ids);
            } else {
                $query->where('dci_id', (int) $dciParam);
            }
        }

        $query->orderBy('name');

        $p = $query->paginate($per);

        $data = collect($p->items())->map(function (PharmaArticle $a) {
            $image = $a->image_url; // URL publique OU null (le front gère le fallback)
            return [
                'id'            => (int) $a->id,
                'code'          => (string) ($a->code ?? ''),
                'name'          => (string) ($a->name ?? ''),
                'form'          => $a->form,
                'dosage'        => $a->dosage,
                'unit'          => $a->unit,

                'dci'           => $a->relationLoaded('dci') ? [
                    'id'   => $a->dci?->id,
                    'name' => $a->dci?->name
                ] : null,

                'stock_on_hand' => (int) ($a->stock_on_hand ?? 0),

                // 💰 prix
                'buy_price'     => $a->buy_price  !== null ? (float) $a->buy_price  : null,
                'sell_price'    => $a->sell_price !== null ? (float) $a->sell_price : null,
                'tax_rate'      => $a->tax_rate   !== null ? (float) $a->tax_rate   : null,

                // 🖼️ image
                'image_url'     => $image,
                'image'         => $image, // alias pour le front

                // ✅ statut stock
                'stock_status'  => $a->stock_status,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => (int) $p->currentPage(),
                'last_page'    => (int) $p->lastPage(),
                'per_page'     => (int) $p->perPage(),
                'total'        => (int) $p->total(),
            ],
        ]);
    }

    // GET /pharma/articles/options?q=&form=&active=1&limit=&dci_id= (ou dci_id[]=)
    public function options(Request $request)
    {
        $q      = trim((string) ($request->query('q') ?? $request->query('search', '')));
        $form   = trim((string) ($request->query('form') ?? $request->query('forme', '')));
        $active = $request->query('active');
        $limit  = max(1, min((int) $request->query('limit', 100), 500));

        $query = PharmaArticle::query()
            ->with('dci:id,name')
            ->withSum('lots as stock_on_hand', 'quantity')
            ->orderBy('name');

        if ($q !== '')    $query->search($q);
        if ($form !== '') $query->where('form', $form);

        if (!is_null($active)) {
            $activeBool = filter_var($active, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if (!is_null($activeBool)) {
                $query->where('is_active', $activeBool);
            }
        }

        // ← AJOUT : filtre DCI (int ou tableau)
        $dciParam = $request->query('dci_id');
        if (!is_null($dciParam) && $dciParam !== '') {
            if (is_array($dciParam)) {
                $ids = array_values(array_filter(array_map('intval', $dciParam), fn($v) => $v > 0));
                if ($ids) $query->whereIn('dci_id', $ids);
            } else {
                $query->where('dci_id', (int) $dciParam);
            }
        }

        $items = $query->limit($limit)->get();

        $out = $items->map(function (PharmaArticle $a) {
            $price = $a->sell_price !== null ? (float) $a->sell_price : null;
            $tax   = $a->tax_rate   !== null ? (float) $a->tax_rate   : null;

            $labelParts = [];
            if ($a->name) $labelParts[] = $a->name;
            elseif ($a->dci?->name) $labelParts[] = $a->dci->name;
            if ($a->dosage) $labelParts[] = trim($a->dosage.' '.($a->unit ?? ''));
            $label = trim(implode(' ', array_filter($labelParts)));
            if ($a->form) $label .= ' ('.$a->form.')';
            if ($label === '') $label = $a->code ?? 'Article';

            $image = $a->image_url;

            return [
                'value'         => (int) $a->id,
                'label'         => $label,

                'id'            => (int) $a->id,
                'code'          => (string) ($a->code ?? ''),
                'name'          => (string) ($a->name ?? ''),
                'dci'           => $a->relationLoaded('dci') && $a->dci ? [
                    'id'   => (int) $a->dci->id,
                    'name' => (string) $a->dci->name
                ] : null,
                'form'          => $a->form,
                'dosage'        => $a->dosage,
                'unit'          => $a->unit,
                'stock_on_hand' => (int) ($a->stock_on_hand ?? 0),

                // 💰 prix
                'buy_price'     => $a->buy_price  !== null ? (float) $a->buy_price  : null,
                'price'         => $price,   // compat front si "price" est lu
                'sell_price'    => $price,
                'tax_rate'      => $tax,

                // 🖼️ image
                'image_url'     => $image,
                'image'         => $image, // alias

                // ✅ statut stock
                'stock_status'  => $a->stock_status,
            ];
        })->values();

        return response()->json($out);
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'dci_id'     => ['nullable','exists:dcis,id'],
            'name'       => ['sometimes','nullable','string','max:190'], // auto depuis DCI si vide
            'code'       => ['sometimes','nullable','string','max:100','unique:pharma_articles,code'], // auto si vide
            'form'       => ['nullable','string','max:100'],
            'dosage'     => ['nullable','string','max:100'],
            'unit'       => ['nullable','string','max:50'],
            'pack_size'  => ['nullable','integer','min:1'],
            'is_active'  => ['sometimes','boolean'],
            'min_stock'  => ['nullable','integer','min:0'],
            'max_stock'  => ['nullable','integer','min:0'],
            'buy_price'  => ['nullable','numeric','min:0'],
            'sell_price' => ['nullable','numeric','min:0'],
            'tax_rate'   => ['nullable','numeric','min:0'],

            // images
            'image'         => ['sometimes','file','image','mimes:jpeg,png,webp,svg','max:4096'],
            'image_base64'  => ['sometimes','string'],
        ]);

        // Fichier (multipart)
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('pharma/articles', 'public');
        }
        // Base64 (JSON)
        elseif ($request->filled('image_base64')) {
            if ($path = $this->storeBase64Image($request->string('image_base64'))) {
                $data['image_path'] = $path;
            }
        }

        $a = PharmaArticle::create($data);

        return $this->show($a);
    }

    public function show(PharmaArticle $article)
    {
        $article->load([
            'dci:id,name',
            'lots' => function ($q) {
                $q->select('id','article_id','lot_number','expires_at','quantity','sell_price')
                  ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END, expires_at ASC, id ASC');
            },
        ]);

        $stockOnHand = (int) $article->lots->sum('quantity');
        $image = $article->image_url;

        $data = [
            'id'            => (int) $article->id,
            'code'          => (string) ($article->code ?? ''),
            'name'          => (string) ($article->name ?? ''),
            'form'          => $article->form,
            'dosage'        => $article->dosage,
            'unit'          => $article->unit,
            'dci_id'        => $article->dci_id ? (int) $article->dci_id : null,
            'dci'           => $article->relationLoaded('dci') && $article->dci
                                ? ['id' => (int) $article->dci->id, 'name' => (string) $article->dci->name]
                                : null,

            'stock_on_hand' => $stockOnHand,

            // 💰 prix
            'buy_price'     => $article->buy_price  !== null ? (float) $article->buy_price  : null,
            'sell_price'    => $article->sell_price !== null ? (float) $article->sell_price : null,
            'tax_rate'      => $article->tax_rate   !== null ? (float) $article->tax_rate   : null,

            // 🖼️ image
            'image_url'     => $image,
            'image'         => $image,

            // ✅ statut stock
            'stock_status'  => $article->stock_status,

            // Lots (fallback sur prix article)
            'lots'          => $article->lots->map(function ($l) use ($article) {
                return [
                    'id'         => (int) $l->id,
                    'lot_number' => (string) $l->lot_number,
                    'expires_at' => optional($l->expires_at)->toDateString(),
                    'quantity'   => (int) $l->quantity,
                    'sell_price' => $l->sell_price !== null
                        ? (float) $l->sell_price
                        : ($article->sell_price !== null ? (float) $article->sell_price : 0.0),
                ];
            })->values(),
        ];

        return response()->json($data);
    }

    public function update(Request $request, PharmaArticle $article)
    {
        $data = $request->validate([
            'dci_id'     => ['nullable','exists:dcis,id'],
            'name'       => ['sometimes','nullable','string','max:190'],
            'code'       => ['sometimes','nullable','string','max:100', Rule::unique('pharma_articles','code')->ignore($article->id)],
            'form'       => ['nullable','string','max:100'],
            'dosage'     => ['nullable','string','max:100'],
            'unit'       => ['nullable','string','max:50'],
            'pack_size'  => ['nullable','integer','min:1'],
            'is_active'  => ['sometimes','boolean'],
            'min_stock'  => ['nullable','integer','min:0'],
            'max_stock'  => ['nullable','integer','min:0'],
            'buy_price'  => ['nullable','numeric','min:0'],
            'sell_price' => ['nullable','numeric','min:0'],
            'tax_rate'   => ['nullable','numeric','min:0'],

            // images
            'image'         => ['sometimes','file','image','mimes:jpeg,png,webp,svg','max:4096'],
            'image_base64'  => ['sometimes','string'],
            'remove_image'  => ['sometimes','boolean'],
        ]);

        // 1) suppression demandée ?
        if ($request->boolean('remove_image') && $article->image_path) {
            Storage::disk('public')->delete($article->image_path);
            $data['image_path'] = null;
        }

        // 2) fichier multipart
        if ($request->hasFile('image')) {
            if ($article->image_path) {
                Storage::disk('public')->delete($article->image_path);
            }
            $data['image_path'] = $request->file('image')->store('pharma/articles', 'public');
        }
        // 3) base64 JSON
        elseif ($request->filled('image_base64')) {
            if ($article->image_path) {
                Storage::disk('public')->delete($article->image_path);
            }
            if ($path = $this->storeBase64Image($request->string('image_base64'))) {
                $data['image_path'] = $path;
            }
        }

        $article->update($data);

        return $this->show($article);
        
    }

    public function destroy(PharmaArticle $article)
    {
        if ($article->image_path) {
            Storage::disk('public')->delete($article->image_path);
        }

        $article->delete();
        return response()->json(['message' => 'Article supprimé']);
    }

    /**
     * Endpoint dédié upload image (POST /pharma/articles/{article}/image)
     * -> ajoute la route si tu souhaites l'utiliser.
     */
    public function updateImage(Request $request, PharmaArticle $article)
    {
        // ✅ 1) Valider le fichier
        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp,svg', 'max:4096'], // max en Ko
        ]);

        try {
            /** @var \Illuminate\Http\UploadedFile $file */
            $file = $validated['image'];

            // ✅ 2) Uploader la nouvelle image sur le disque "public"
            //    (assure-toi d'avoir fait: php artisan storage:link)
            $newPath = $file->store('pharma/articles', 'public'); // ex: storage/app/public/pharma/articles/xxx.png

            // ✅ 3) Supprimer l'ancienne image si elle existe et si elle est différente
            if (!empty($article->image_path) && $article->image_path !== $newPath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($article->image_path);
            }

            // ✅ 4) Sauver le chemin en base (colonne image_path)
            $article->update(['image_path' => $newPath]);

            // ✅ 5) Répondre avec des infos utiles
            return response()->json([
                'id'         => (int) $article->id,
                'image_path' => $article->image_path, // chemin relatif sur le disque public
                'image_url'  => $article->image_url,  // URL publique via accessor du modèle
                // Bonus informatifs (facultatif)
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getClientMimeType(),
                'size'          => $file->getSize(), // en octets
            ], 200);

        } catch (\Throwable $e) {
            // ❌ Si quelque chose se passe mal (permissions, disque manquant, etc.)
            return response()->json([
                'message' => 'Échec upload image',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    // ---------------------------------------------------------------------
    // ↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓  AJOUTS  ↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
    // ---------------------------------------------------------------------

    // GET /api/v1/pharma/dcis/{dci}/articles
    public function byDci(Request $r, Dci $dci)
    {
        $per = max(1, min((int) $r->query('per_page', 32), 100));

        $rows = PharmaArticle::query()
            ->where('dci_id', $dci->id)
            ->with('dci:id,name')
            ->withSum('lots as stock_on_hand', 'quantity')
            ->orderBy('name')
            ->paginate($per);

        $rows->getCollection()->transform(function (PharmaArticle $a) {
            return [
                'id'            => (int) $a->id,
                'code'          => (string) ($a->code ?? ''),
                'name'          => (string) ($a->name ?? ''),
                'form'          => $a->form,
                'dosage'        => $a->dosage,
                'unit'          => $a->unit,
                'stock_on_hand' => (int) ($a->stock_on_hand ?? 0),
                'sell_price'    => $a->sell_price !== null ? (float) $a->sell_price : null,
                'tax_rate'      => $a->tax_rate   !== null ? (float) $a->tax_rate   : null,
                'image_url'     => $a->image_url,
            ];
        });

        return response()->json([
            'dci'  => ['id' => (int) $dci->id, 'name' => (string) $dci->name],
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
        ]);
    }

    // GET /api/v1/pharma/substitutes?article_id=123
    public function substitutes(Request $r)
    {
        $articleId = (int) $r->query('article_id');
        abort_if(!$articleId, 422, 'article_id requis');

        $base = PharmaArticle::with('dci:id,name')->findOrFail($articleId);
        abort_if(!$base->dci_id, 422, 'Cet article n’a pas de DCI associée');

        $rows = PharmaArticle::query()
            ->where('dci_id', $base->dci_id)
            ->where('id', '!=', $base->id)
            ->withSum('lots as stock_on_hand', 'quantity')
            ->orderByRaw('CASE WHEN COALESCE(stock_on_hand,0) > 0 THEN 0 ELSE 1 END')
            ->orderBy('sell_price')
            ->orderBy('name')
            ->limit(20)
            ->get(['id','code','name','form','dosage','unit','sell_price','tax_rate']);

        return response()->json([
            'base' => [
                'id'   => (int) $base->id,
                'name' => (string) ($base->name ?? ''),
                'dci'  => $base->dci?->name,
            ],
            'alternatives' => $rows->map(function ($a) {
                return [
                    'id'         => (int) $a->id,
                    'code'       => (string) ($a->code ?? ''),
                    'name'       => (string) ($a->name ?? ''),
                    'form'       => $a->form,
                    'dosage'     => $a->dosage,
                    'unit'       => $a->unit,
                    'stock'      => (int) ($a->stock_on_hand ?? 0),
                    'sell_price' => $a->sell_price !== null ? (float) $a->sell_price : null,
                ];
            })->values(),
        ]);
    }

    // --------- Helpers privés ---------

    /**
     * Stocke une image envoyée en data URL base64 et retourne le chemin public (disk 'public').
     */
    private function storeBase64Image(?string $b64): ?string
    {
        if (!$b64) return null;
        if (!preg_match('/^data:image\/([a-zA-Z0-9.+-]+);base64,/', $b64, $m)) {
            return null; // format non reconnu
        }
        $ext  = strtolower($m[1]); // ex: png, jpeg, webp, svg+xml
        $ext  = str_replace('svg+xml', 'svg', $ext);

        $data = base64_decode(substr($b64, strpos($b64, ',') + 1), true);
        if ($data === false) return null;

        // limite ~4 Mo
        if (strlen($data) > 4 * 1024 * 1024) {
            abort(422, 'Image trop volumineuse (max 4 Mo).');
        }

        $filename = Str::uuid().'.'.$ext;
        $path = 'pharma/articles/'.$filename;

        Storage::disk('public')->put($path, $data);

        return $path;
    }

        /**
     * GET /api/v1/pharma/articles/{article}/equivalents
     * Retourne les articles partageant le même dci_id que $article (équivalents),
     * en excluant l’article courant. Filtrable/paginé comme index().
     *
     * Query params optionnels:
     * - per_page (default 20, max 100)
     * - active (true/false)
     * - q (recherche)
     * - form (forme galénique)
     */
    public function equivalents(Request $request, PharmaArticle $article)
    {
        // Si pas de DCI, pas d'équivalents
        if (!$article->dci_id) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page'    => 1,
                    'per_page'     => (int) ($request->query('per_page', 20)),
                    'total'        => 0,
                ],
            ]);
        }

        $q      = trim((string) ($request->query('q') ?? $request->query('search', '')));
        $form   = trim((string) ($request->query('form') ?? $request->query('forme', '')));
        $active = $request->query('active');
        $per    = max(1, min((int) $request->query('per_page', 20), 100));

        $query = \App\Models\Pharmacie\PharmaArticle::query()
            ->with('dci:id,name')
            ->withSum('lots as stock_on_hand', 'quantity')
            ->where('dci_id', $article->dci_id)
            ->where('id', '<>', $article->id);

        if ($q !== '')    $query->search($q);
        if ($form !== '') $query->where('form', $form);
        if (!is_null($active)) {
            $activeBool = filter_var($active, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if (!is_null($activeBool)) $query->where('is_active', $activeBool);
        }

        $query->orderBy('name');

        $p = $query->paginate($per);

        // 🔁 Fallback: si aucun équivalent, on peut renvoyer l'article courant
        $includeSelfIfAlone = $request->boolean('include_self_if_alone', true);
        if ($p->total() === 0 && $includeSelfIfAlone) {
            $article->loadMissing(['dci:id,name'])->loadSum('lots as stock_on_hand', 'quantity');
            $image = $article->image_url;
            return response()->json([
                'data' => [[
                    'id'            => (int) $article->id,
                    'code'          => (string) ($article->code ?? ''),
                    'name'          => (string) ($article->name ?? ''),
                    'form'          => $article->form,
                    'dosage'        => $article->dosage,
                    'unit'          => $article->unit,
                    'dci'           => $article->dci ? [
                        'id' => (int) $article->dci->id,
                        'name' => (string) $article->dci->name
                    ] : null,
                    'stock_on_hand' => (int) ($article->stock_on_hand ?? 0),
                    'buy_price'     => $article->buy_price  !== null ? (float) $article->buy_price  : null,
                    'sell_price'    => $article->sell_price !== null ? (float) $article->sell_price : null,
                    'tax_rate'      => $article->tax_rate   !== null ? (float) $article->tax_rate   : null,
                    'image_url'     => $image,
                    'image'         => $image,
                ]],
                'meta' => [
                    'current_page' => 1,
                    'last_page'    => 1,
                    'per_page'     => $per,
                    'total'        => 1,
                ],
            ]);
        }

        $data = collect($p->items())->map(function (\App\Models\Pharmacie\PharmaArticle $a) {
            $image = $a->image_url;
            return [
                'id'            => (int) $a->id,
                'code'          => (string) ($a->code ?? ''),
                'name'          => (string) ($a->name ?? ''),
                'form'          => $a->form,
                'dosage'        => $a->dosage,
                'unit'          => $a->unit,

                'dci'           => $a->relationLoaded('dci') ? [
                    'id'   => $a->dci?->id,
                    'name' => $a->dci?->name
                ] : null,

                'stock_on_hand' => (int) ($a->stock_on_hand ?? 0),

                'buy_price'     => $a->buy_price  !== null ? (float) $a->buy_price  : null,
                'sell_price'    => $a->sell_price !== null ? (float) $a->sell_price : null,
                'tax_rate'      => $a->tax_rate   !== null ? (float) $a->tax_rate   : null,

                'image_url'     => $image,
                'image'         => $image,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page'    => $p->lastPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
            ],
        ]);
    }


}
