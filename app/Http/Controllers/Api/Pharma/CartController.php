<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Controller;
use App\Models\Pharmacie\PharmaArticle;
use App\Models\Pharmacie\PharmaCart;
use App\Models\Pharmacie\PharmaCartLine;
use App\Models\Pharmacie\PharmaLot;
use App\Models\Pharmacie\PharmaStockMovement;
use App\Services\Pharmacie\PharmacyFactureService; // <-- bon namespace
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    // POST /api/v1/pharma/carts
    public function store(Request $request)
    {
        $data = $request->validate([
            'visite_id'     => ['nullable','uuid','exists:visites,id'],
            'patient_id'    => ['nullable','uuid'],
            'customer_name' => ['nullable','string','max:190'],
            'currency'      => ['nullable','string','max:10'],
        ]);

        $cart = PharmaCart::create([
            'user_id'       => $request->user()?->id,
            'status'        => 'open',
            'visite_id'     => $data['visite_id']     ?? null,
            'patient_id'    => $data['patient_id']    ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'currency'      => $data['currency']      ?? 'XAF',
        ]);

        return response()->json($cart, 201);
    }

    // GET /api/v1/pharma/carts/{cart}
    public function show(PharmaCart $cart)
    {
        $cart->load(['lines.article:id,name,code,sell_price,tax_rate','invoice:id,numero']);
        return response()->json($cart);
    }

    // POST /api/v1/pharma/carts/{cart}/lines
    public function addLine(Request $request, PharmaCart $cart)
    {
        $this->assertOpen($cart);

        $data = $request->validate([
            'article_id' => ['required','exists:pharma_articles,id'],
            'quantity'   => ['required','integer','min:1'],
            'unit_price' => ['nullable','numeric','min:0'],
            'tax_rate'   => ['nullable','numeric','min:0'],
        ]);

        $article = PharmaArticle::findOrFail($data['article_id']);

        $line = PharmaCartLine::firstOrNew([
            'cart_id'    => $cart->id,
            'article_id' => $article->id,
        ]);

        $line->quantity   = ($line->exists ? $line->quantity : 0) + (int)$data['quantity'];
        $line->unit_price = $data['unit_price'] ?? $line->unit_price ?? ($article->sell_price ?? 0);
        $line->tax_rate   = $data['tax_rate']   ?? $line->tax_rate   ?? ($article->tax_rate   ?? 0);

        $line->line_ht  = $line->quantity * $line->unit_price;
        $line->line_tva = round($line->line_ht * ($line->tax_rate/100), 2);
        $line->line_ttc = $line->line_ht + $line->line_tva;
        $line->save();

        $this->recalcTotals($cart);

        return response()->json($line->load('article:id,name,code,sell_price,tax_rate'), 201);
    }

    // PATCH /api/v1/pharma/carts/{cart}/lines/{line}
    public function updateLine(Request $request, PharmaCart $cart, PharmaCartLine $line)
    {
        $this->assertOpen($cart);
        if ($line->cart_id !== $cart->id) abort(404);

        $data = $request->validate([
            'quantity'   => ['sometimes','integer','min:1'],
            'unit_price' => ['sometimes','numeric','min:0'],
            'tax_rate'   => ['sometimes','numeric','min:0'],
        ]);

        $line->fill($data);

        $line->line_ht  = $line->quantity * ($line->unit_price ?? 0);
        $line->line_tva = round($line->line_ht * (($line->tax_rate ?? 0)/100), 2);
        $line->line_ttc = $line->line_ht + $line->line_tva;
        $line->save();

        $this->recalcTotals($cart);

        return response()->json($line->load('article:id,name,code,sell_price,tax_rate'));
    }

    // DELETE /api/v1/pharma/carts/{cart}/lines/{line}
    public function removeLine(PharmaCart $cart, PharmaCartLine $line)
    {
        $this->assertOpen($cart);
        if ($line->cart_id !== $cart->id) abort(404);

        $line->delete();
        $this->recalcTotals($cart);

        return response()->json(['message' => 'Ligne supprimée']);
    }

    /**
     * POST /api/v1/pharma/carts/{cart}/checkout
     * - décrément FIFO
     * - crée la facture locale
     * - lie invoice_id et clôture le panier
     */
    public function checkout(Request $request, PharmaCart $cart, PharmacyFactureService $factures)
    {
        $this->assertOpen($cart);

        $payload = $request->validate([
            'reference' => ['nullable','string','max:190'],
        ]);

        $cart->load('lines.article');

        if ($cart->lines()->count() === 0) {
            throw ValidationException::withMessages(['cart' => 'Panier vide']);
        }

        DB::beginTransaction();
        try {
            // 1) FIFO & décrément des lots
            foreach ($cart->lines as $line) {
                $remaining = $line->quantity;

                $lots = PharmaLot::where('article_id', $line->article_id)
                    ->where('quantity', '>', 0)
                    ->orderBy('expires_at')->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($lots as $lot) {
                    if ($remaining <= 0) break;

                    $take = min($remaining, $lot->quantity);
                    if ($take <= 0) continue;

                    $lot->quantity -= $take;
                    $lot->save();

                    PharmaStockMovement::create([
                        'article_id' => $line->article_id,
                        'lot_id'     => $lot->id,
                        'type'       => 'out',
                        'quantity'   => $take,
                        'unit_price' => $line->unit_price,
                        'reason'     => 'sale',
                        'reference'  => $payload['reference'] ?? ("PHARMA-CART-{$cart->id}"),
                        'user_id'    => $request->user()?->id,
                    ]);

                    $remaining -= $take;
                }

                if ($remaining > 0) {
                    throw ValidationException::withMessages([
                        'stock' => "Stock insuffisant pour l'article ID {$line->article_id}, manque {$remaining}."
                    ]);
                }
            }

            // 2) Totaux panier (sécurité)
            $this->recalcTotals($cart);

            // 3) Créer la facture (retourne l'objet Facture)
            $facture = $factures->createFromCart($cart);

            // 4) Clôturer + lier la facture
            $cart->forceFill([
                'status'     => 'checked_out',
                'invoice_id' => $facture->id,   // <-- on garde la trace
            ])->save();

            DB::commit();

            return response()->json([
                'message' => 'Checkout OK',
                'facture' => $facture,
                'cart'    => $cart->fresh()->load(['lines.article:id,name,code','invoice:id,numero']),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Echec checkout: '.$e->getMessage()], 422);
        }
    }

    // ── Utils ─────────────────────────────────────────────────────────────
    protected function assertOpen(PharmaCart $cart): void
    {
        if ($cart->status !== 'open') {
            abort(422, 'Le panier n’est pas ouvert.');
        }
    }

    protected function recalcTotals(PharmaCart $cart): void
    {
        $totals = $cart->lines()
            ->selectRaw('SUM(line_ht) as ht, SUM(line_tva) as tva, SUM(line_ttc) as ttc')
            ->first();

        $cart->total_ht  = (float) ($totals->ht  ?? 0);
        $cart->total_tva = (float) ($totals->tva ?? 0);
        $cart->total_ttc = (float) ($totals->ttc ?? 0);
        $cart->save();
    }
}
