<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Controller;
use App\Models\Pharmacie\PharmaArticle;
use App\Models\Pharmacie\PharmaLot;
use App\Models\Pharmacie\PharmaStockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | IN (réception)
    |--------------------------------------------------------------------------
    | - Si unit_price / sell_price absents → on n'écrase pas, le lot hérite
    |   de l’article via PharmaLot::booted() côté modèle
    | - Si le lot existe, on n’écrase pas ses prix sauf si la requête fournit
    |   explicitement unit_price ou sell_price
    */
    public function in(Request $request)
    {
        $data = $request->validate([
            'article_id' => ['required','exists:pharma_articles,id'],
            'lot_number' => ['required','string','max:100'],
            'expires_at' => ['nullable','date'],
            'quantity'   => ['required','integer','min:1'],
            'unit_price' => ['nullable','numeric','min:0'], // buy_price
            'sell_price' => ['nullable','numeric','min:0'], // prix vente par lot (optionnel)
            'supplier'   => ['nullable','string','max:190'],
            'reference'  => ['nullable','string','max:190'],
        ]);

        return DB::transaction(function () use ($data, $request) {
            $article = PharmaArticle::findOrFail($data['article_id']);

            // Valeurs utilisées pour ce mouvement / lot
            $buy  = array_key_exists('unit_price', $data) ? $data['unit_price'] : $article->buy_price;
            $sell = array_key_exists('sell_price', $data) ? $data['sell_price'] : $article->sell_price;

            // Créer ou récupérer le lot (clé composite article_id + lot_number)
            $lot = PharmaLot::firstOrCreate(
                ['article_id' => $article->id, 'lot_number' => trim($data['lot_number'])],
                [
                    'expires_at' => $data['expires_at'] ?? null,
                    'quantity'   => 0,
                    'buy_price'  => $buy,
                    'sell_price' => $sell,
                    'supplier'   => $data['supplier'] ?? null,
                ]
            );

            // Si le lot existait déjà, n’écrase pas ses prix par défaut,
            // mais accepte les prix explicitement fournis
            if (array_key_exists('unit_price', $data)) $lot->buy_price  = $buy;
            if (array_key_exists('sell_price', $data)) $lot->sell_price = $sell;
            if (array_key_exists('expires_at',$data)) $lot->expires_at  = $data['expires_at'];
            if (array_key_exists('supplier',  $data)) $lot->supplier    = $data['supplier'];

            $lot->quantity += (int) $data['quantity'];
            $lot->save();

            // Mouvement IN (le coût d’entrée = buy)
            $mv = PharmaStockMovement::create([
                'article_id' => $article->id,
                'lot_id'     => $lot->id,
                'type'       => 'in', // minuscule uniforme
                'quantity'   => (int) $data['quantity'],
                'unit_price' => $buy,
                'reason'     => 'reception',
                'reference'  => $data['reference'] ?? null,
                'user_id'    => $request->user()?->id,
            ]);

            return response()->json([
                'message'  => 'Entrée stock enregistrée',
                'lot'      => $lot->fresh(),
                'movement' => $mv,
                'stock'    => $this->stockWarnings($article),
                'prices'   => ['buy_used' => (float) $buy, 'sell_used' => (float) $sell],
            ], 201);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | OUT (vente / transfert / casse) – FEFO
    |--------------------------------------------------------------------------
    | - Priorité aux lots qui expirent le plus tôt (NULL à la fin)
    | - Ignore les lots périmés (sécurité)
    | - Prix unitaire = sell_price du lot, sinon sell_price article, sinon 0
    */
    public function out(Request $request)
    {
        $data = $request->validate([
            'article_id' => ['required','exists:pharma_articles,id'],
            'quantity'   => ['required','integer','min:1'],
            'reason'     => ['nullable','in:sale,transfer,waste'],
            'reference'  => ['nullable','string','max:190'],
        ]);

        return DB::transaction(function () use ($data, $request) {
            $article   = PharmaArticle::findOrFail($data['article_id']);
            $remaining = (int) $data['quantity'];
            $movements = [];

            $lots = PharmaLot::where('article_id', $article->id)
                ->where('quantity','>',0)
                // ignorer périmés
                ->where(function($w){
                    $w->whereNull('expires_at')->orWhere('expires_at', '>=', now()->toDateString());
                })
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END, expires_at ASC, id ASC')
                ->lockForUpdate()
                ->get();

            foreach ($lots as $lot) {
                if ($remaining <= 0) break;

                $take = min($remaining, $lot->quantity);
                if ($take <= 0) continue;

                $lot->quantity -= $take;
                $lot->save();

                $unitPrice = $lot->sell_price ?? $article->sell_price ?? 0;

                $movements[] = PharmaStockMovement::create([
                    'article_id' => $article->id,
                    'lot_id'     => $lot->id,
                    'type'       => 'out', // minuscule uniforme
                    'quantity'   => $take,
                    'unit_price' => $unitPrice,
                    'reason'     => $data['reason'] ?? 'sale',
                    'reference'  => $data['reference'] ?? null,
                    'user_id'    => $request->user()?->id,
                ]);

                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Stock insuffisant. Il manque {$remaining} unité(s)."
                ]);
            }

            // ✅ enrichir la réponse avec lot_number / expires_at (sans appeler ->load sur la collection)
            foreach ($movements as $m) {
                $m->load('lot:id,lot_number,expires_at');
            }

            $movementsPayload = collect($movements)->map(function ($m) {
                return [
                    'id'          => $m->id,
                    'article_id'  => $m->article_id,
                    'lot_id'      => $m->lot_id,
                    'lot_number'  => $m->lot?->lot_number,
                    'expires_at'  => optional($m->lot?->expires_at)->toDateString(),
                    'type'        => $m->type,
                    'quantity'    => (int) $m->quantity,
                    'unit_price'  => number_format((float) $m->unit_price, 2, '.', ''),
                    'reason'      => $m->reason,
                    'reference'   => $m->reference,
                    'user_id'     => $m->user_id,
                    'created_at'  => $m->created_at?->toIso8601String(),
                ];
            });

            return response()->json([
                'message'    => 'Sortie stock enregistrée (FEFO)',
                'dispatched' => (int) $data['quantity'],
                'movements'  => $movementsPayload,
                'stock'      => $this->stockWarnings($article),
            ], 201);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Ajustement inventaire (+/-)
    |--------------------------------------------------------------------------
    */
    public function adjust(Request $request)
    {
        $data = $request->validate([
            'article_id'    => ['required','exists:pharma_articles,id'],
            'lot_id'        => ['required','exists:pharma_lots,id'],
            'quantity_diff' => ['required','integer','not_in:0'],
            'reason'        => ['nullable','string','max:190'],
            'reference'     => ['nullable','string','max:190'],
        ]);

        return DB::transaction(function () use ($data, $request) {
            $lot = PharmaLot::where('id', $data['lot_id'])
                ->where('article_id', $data['article_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $article = $lot->article;

            $newQty = $lot->quantity + (int) $data['quantity_diff'];
            if ($newQty < 0) {
                throw ValidationException::withMessages([
                    'quantity_diff' => 'Ajustement invalide : stock résultant négatif.'
                ]);
            }

            $lot->quantity = $newQty;
            $lot->save();

            $type = $data['quantity_diff'] > 0 ? 'in' : 'out';

            $mv = PharmaStockMovement::create([
                'article_id' => $data['article_id'],
                'lot_id'     => $data['lot_id'],
                'type'       => $type, // minuscule uniforme
                'quantity'   => abs((int) $data['quantity_diff']),
                'unit_price' => null,
                'reason'     => $data['reason'] ?? 'inventory_adjustment',
                'reference'  => $data['reference'] ?? null,
                'user_id'    => $request->user()?->id,
            ]);

            return response()->json([
                'message'  => 'Ajustement enregistré',
                'lot'      => $lot->fresh(),
                'movement' => $mv,
                'stock'    => $this->stockWarnings($article),
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Mouvements (listing) + filtres
    |--------------------------------------------------------------------------
    */
    public function movements(Request $request)
    {
        $per = max(1, min((int) $request->query('per_page', 20), 100));

        $q = PharmaStockMovement::with([
                'article:id,name,code',
                'lot:id,lot_number,expires_at'
            ])
            ->when($request->filled('q'), function ($qq) use ($request) {
                $qq->whereHas('article', function ($w) use ($request) {
                    $w->where('name', 'like', '%'.$request->q.'%')
                      ->orWhere('code', 'like', '%'.$request->q.'%');
                });
            });

        if ($aid = $request->query('article_id')) $q->where('article_id', $aid);
        if ($lid = $request->query('lot_id'))     $q->where('lot_id', $lid);
        if ($type = $request->query('type'))      $q->where('type', $type);
        if ($from = $request->query('from'))      $q->whereDate('created_at', '>=', $from);
        if ($to   = $request->query('to'))        $q->whereDate('created_at', '<=', $to);

        return response()->json($q->orderByDesc('id')->paginate($per));
    }

    /*
    |--------------------------------------------------------------------------
    | Résumé d’un article
    |--------------------------------------------------------------------------
    */
    public function summary(Request $request)
    {
        $article = PharmaArticle::with('lots')->findOrFail((int) $request->query('article_id'));

        $lots = $article->lots()
            ->select('id','lot_number','expires_at','quantity','buy_price','sell_price','supplier')
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END, expires_at ASC, id ASC')
            ->get();

        return response()->json([
            'article'       => $article->only(['id','name','code','sell_price','tax_rate']),
            'stock_on_hand' => (int) $lots->sum('quantity'),
            'lots'          => $lots,
            'stock'         => $this->stockWarnings($article),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Top vendeurs (qty + revenue)
    |--------------------------------------------------------------------------
    */
    public function topSellers(Request $request)
    {
        $limit = max(1, min((int) $request->query('limit', 10), 100));
        $from  = $request->query('from');
        $to    = $request->query('to');

        $rows = PharmaStockMovement::query()
            ->selectRaw("
                article_id,
                SUM(CASE WHEN type='out' THEN quantity ELSE 0 END)                           AS qty_sold,
                SUM(CASE WHEN type='out' THEN COALESCE(unit_price,0) * quantity ELSE 0 END)  AS revenue
            ")
            ->where('type', 'out')
            ->when($from, fn ($qq) => $qq->whereDate('created_at', '>=', $from))
            ->when($to,   fn ($qq) => $qq->whereDate('created_at', '<=', $to))
            ->groupBy('article_id')
            ->orderByDesc('qty_sold')
            ->limit($limit)
            ->get();

        $articles = PharmaArticle::whereIn('id', $rows->pluck('article_id'))
            ->get(['id','name','code','sell_price'])
            ->keyBy('id');

        return response()->json($rows->map(fn ($r) => [
            'article_id' => (int) $r->article_id,
            'qty'        => (int) $r->qty_sold,
            'revenue'    => (float) $r->revenue,
            'article'    => $articles[$r->article_id] ?? null,
        ]));
    }

    /*
    |--------------------------------------------------------------------------
    | Lots les plus anciens (FEFO) – avec fallback prix
    |--------------------------------------------------------------------------
    */
    public function oldestLots(Request $request)
    {
        $limit = max(1, min((int) $request->query('limit', 10), 100));

        $lots = PharmaLot::query()
            ->where('quantity','>',0)
            ->with('article:id,name,code,sell_price')
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END, expires_at ASC, id ASC')
            ->limit($limit)
            ->get(['id','article_id','lot_number','expires_at','quantity','sell_price']);

        return response()->json($lots->map(function ($l) {
            return [
                'id'         => $l->id,
                'article_id' => $l->article_id,
                'lot_number' => $l->lot_number,
                'expires_at' => $l->expires_at,
                'quantity'   => $l->quantity,
                'sell_price' => $l->sell_price ?? $l->article->sell_price ?? 0,
                'article'    => [
                    'id'   => $l->article->id,
                    'code' => $l->article->code,
                    'name' => $l->article->name,
                ],
            ];
        }));
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: état & alertes min/max pour un article
    |--------------------------------------------------------------------------
    */
    private function stockWarnings(PharmaArticle $article): array
    {
        $onHand = (int) $article->lots()->sum('quantity');
        $warn   = [];

        if ($article->min_stock && $onHand < (int) $article->min_stock) {
            $warn[] = "Stock bas: {$onHand} < min ({$article->min_stock}).";
        }
        if ($article->max_stock && $onHand > (int) $article->max_stock) {
            $warn[] = "Surstock: {$onHand} > max ({$article->max_stock}).";
        }

        return [
            'stock_on_hand' => $onHand,
            'min'           => (int) ($article->min_stock ?? 0),
            'max'           => (int) ($article->max_stock ?? 0),
            'warnings'      => $warn,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Alerts (min/max) pour tout le catalogue
    |--------------------------------------------------------------------------
    */
    public function alerts(Request $request)
    {
        $includeOk  = (bool) $request->boolean('include_ok', false);
        $onlyActive = (bool) $request->boolean('only_active', true);

        $stockByArticle = DB::table('pharma_lots')
            ->select('article_id', DB::raw('SUM(quantity) as qty'))
            ->groupBy('article_id');

        $q = DB::table('pharma_articles as a')
            ->leftJoinSub($stockByArticle, 's', 's.article_id', '=', 'a.id')
            ->selectRaw('a.id, a.code, a.name, a.dci_id, a.min_stock, a.max_stock, COALESCE(s.qty,0) as qty');

        if ($onlyActive) $q->where('a.is_active', 1);

        if ($request->filled('q')) {
            $needle = '%'.$request->q.'%';
            $q->where(function($w) use ($needle) {
                $w->where('a.name', 'like', $needle)
                  ->orWhere('a.code', 'like', $needle);
            });
        }

        if ($request->filled('dci_id')) $q->where('a.dci_id', (int)$request->dci_id);

        $rows = $q->orderBy('a.name')->get();

        $below = [];
        $above = [];
        $ok    = [];

        foreach ($rows as $r) {
            $min = (int) $r->min_stock;
            $max = (int) $r->max_stock;
            $qty = (int) $r->qty;

            $record = [
                'article_id' => (int) $r->id,
                'code'       => $r->code,
                'name'       => $r->name,
                'qty'        => $qty,
                'min_stock'  => $min,
                'max_stock'  => $max,
                'status'     => null,
            ];

            if ($min > 0 && $qty < $min) {
                $record['status'] = 'below_min';
                $below[] = $record;
                continue;
            }
            if ($max > 0 && $qty > $max) {
                $record['status'] = 'above_max';
                $above[] = $record;
                continue;
            }

            if ($includeOk) {
                $record['status'] = 'ok';
                $ok[] = $record;
            }
        }

        return response()->json([
            'below_min' => $below,
            'above_max' => $above,
            'ok'        => $includeOk ? $ok : null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/v1/pharma/stock/thresholds
    |--------------------------------------------------------------------------
    */
    public function setThresholds(Request $request)
    {
        $data = $request->validate([
            'article_id' => ['required','exists:pharma_articles,id'],
            'min_stock'  => ['nullable','integer','min:0'],
            'max_stock'  => ['nullable','integer','min:0'],
        ]);

        $upd = [];
        if ($request->has('min_stock')) $upd['min_stock'] = (int) $data['min_stock'];
        if ($request->has('max_stock')) $upd['max_stock'] = (int) $data['max_stock'];

        if ($upd === []) {
            return response()->json(['message' => 'Aucun champ à mettre à jour'], 422);
        }

        DB::table('pharma_articles')->where('id', $data['article_id'])->update($upd);

        $article = PharmaArticle::findOrFail($data['article_id']);

        return response()->json([
            'message' => 'Seuils mis à jour',
            'article' => [
                'id'        => $article->id,
                'code'      => $article->code,
                'name'      => $article->name,
                'min_stock' => (int) $article->min_stock,
                'max_stock' => (int) $article->max_stock,
            ],
            'stock' => $this->stockWarnings($article),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/v1/pharma/lots  (debug/visualisation FEFO)
    |--------------------------------------------------------------------------
    */
    public function lots(Request $request)
    {
        $data = $request->validate([
            'article_id'      => ['required','exists:pharma_articles,id'],
            'include_expired' => ['sometimes','boolean'],
        ]);

        $includeExpired = (bool) ($data['include_expired'] ?? false);

        $query = PharmaLot::query()
            ->where('article_id', $data['article_id'])
            ->where('quantity', '>', 0)
            ->with('article:id,name,code,sell_price')
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END, expires_at ASC, id ASC');

        if (! $includeExpired) {
            $query->where(function($w){
                $w->whereNull('expires_at')->orWhere('expires_at', '>=', now()->toDateString());
            });
        }

        $lots = $query->get(['id','article_id','lot_number','expires_at','quantity','sell_price']);

        $payload = $lots->map(function ($l) {
            return [
                'id'         => $l->id,
                'lot_number' => $l->lot_number,
                'expires_at' => optional($l->expires_at)->toDateString(),
                'quantity'   => (int) $l->quantity,
                'sell_price' => (float) ($l->sell_price ?? $l->article->sell_price ?? 0),
            ];
        });

        return response()->json([
            'article'        => $lots->first()?->article?->only(['id','code','name']),
            'total_on_hand'  => (int) $lots->sum('quantity'),
            'include_expired'=> $includeExpired,
            'lots'           => $payload,
        ]);
    }
}
