<?php

namespace App\Services\Pharmacie;

use App\Models\Pharmacie\PharmaArticle;
use App\Models\Pharmacie\PharmaLot;
use App\Models\Pharmacie\PharmaStockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Réception de stock (IN) : crée/maj le lot, incrémente quantity et trace un mouvement IN.
     * NOTE: Le prix de vente de lot (sell_price) est optionnel ; s'il est null,
     *       PharmaLot::booted() hérite automatiquement de l'article.
     */
    public function receive(array $data): PharmaLot
    {
        return DB::transaction(function () use ($data) {
            // Nettoyage et validations minimales
            $data['lot_number'] = trim((string)($data['lot_number'] ?? ''));
            if ($data['lot_number'] === '') {
                throw ValidationException::withMessages(['lot_number' => 'Le numéro de lot est requis.']);
            }

            $qtyIn = (int) ($data['quantity'] ?? 0);
            if ($qtyIn <= 0) {
                throw ValidationException::withMessages(['quantity' => 'La quantité reçue doit être > 0.']);
            }

            /** @var PharmaLot $lot */
            $lot = PharmaLot::firstOrCreate(
                ['article_id' => $data['article_id'], 'lot_number' => $data['lot_number']],
                ['expires_at' => $data['expires_at'] ?? null, 'quantity' => 0]
            );

            // MAJ infos du lot (hérite des prix de l’article si null via booted())
            $lot->quantity   += $qtyIn;
            $lot->buy_price   = $data['buy_price']  ?? $lot->buy_price;
            // ne touche pas sell_price si non fourni : héritage auto via booted()
            if (array_key_exists('sell_price', $data)) {
                $lot->sell_price = $data['sell_price'];
            }
            $lot->supplier    = $data['supplier']   ?? $lot->supplier;
            $lot->expires_at  = $data['expires_at'] ?? $lot->expires_at;
            $lot->save();

            // Mouvement IN (coût d’entrée = buy_price snapshot)
            PharmaStockMovement::create([
                'article_id' => $lot->article_id,
                'lot_id'     => $lot->id,
                'type'       => 'in', // << minuscule uniforme
                'quantity'   => $qtyIn,
                'unit_price' => $lot->buy_price,
                'reason'     => $data['reason']    ?? 'reception',
                'reference'  => $data['reference'] ?? null,      // ex: BL, facture fournisseur
                'user_id'    => $data['user_id']   ?? null,
            ]);

            return $lot;
        });
    }

    /**
     * Consommation de stock (OUT) en FEFO/FIFO.
     *
     * @param PharmaArticle $article  Article concerné
     * @param int           $quantity Quantité à sortir (>0)
     * @param array         $meta     ['reason','reference','user_id'] (facultatif)
     * @param bool          $ignoreExpired  true = ignorer lots périmés (recommandé)
     *
     * @return array<int, array{lot_id:int, lot_number:?string, expires_at:?string, taken:int, unit_price:string}>
     *
     * @throws \Illuminate\Validation\ValidationException si stock insuffisant
     */
    public function consume(PharmaArticle $article, int $quantity, array $meta = [], bool $ignoreExpired = true): array
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La quantité doit être > 0.']);
        }

        return DB::transaction(function () use ($article, $quantity, $meta, $ignoreExpired) {
            $remaining = $quantity;
            $used = [];

            // Sélection FEFO/FIFO + verrouillage
            $query = $article->lots()
                ->available()
                ->FIFO()           // expires_at ASC, NULL en dernier, puis id
                ->lockForUpdate(); // évite la double conso si plusieurs requêtes simultanées

            if ($ignoreExpired) {
                $query->notExpired(); // filtre les lots expirés
            }

            /** @var \Illuminate\Database\Eloquent\Collection<int,PharmaLot> $lots */
            $lots = $query->get();

            foreach ($lots as $lot) {
                if ($remaining <= 0) break;

                $take = min($remaining, (int) $lot->quantity);
                if ($take <= 0) continue;

                // Décrément du lot
                $lot->decrement('quantity', $take);

                // Prix unitaire snapshot (vente)
                $unitSell = $lot->sell_price ?? $article->sell_price ?? 0;

                // Mouvement OUT (un par lot consommé)
                PharmaStockMovement::create([
                    'article_id' => $article->id,
                    'lot_id'     => $lot->id,
                    'type'       => 'out', // << minuscule uniforme
                    'quantity'   => $take,
                    'unit_price' => $unitSell,
                    'reason'     => $meta['reason']    ?? 'sale',
                    'reference'  => $meta['reference'] ?? null,    // ex: numéro de facture/commande
                    'user_id'    => $meta['user_id']   ?? null,
                ]);

                $used[] = [
                    'lot_id'     => $lot->id,
                    'lot_number' => $lot->lot_number,
                    'expires_at' => $lot->expires_at?->toDateString(),
                    'taken'      => $take,
                    'unit_price' => number_format((float) $unitSell, 2, '.', ''),
                ];

                $remaining -= $take;
            }

            if ($remaining > 0) {
                // rollback auto grâce à la transaction
                $dispo = $quantity - $remaining;
                throw ValidationException::withMessages([
                    'quantity' => "Stock insuffisant pour l'article {$article->id} : demandé {$quantity}, disponible {$dispo}."
                ]);
            }

            return $used;
        });
    }
}
