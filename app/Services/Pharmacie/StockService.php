<?php

namespace App\Services\Pharmacie;

use App\Models\Pharmacie\Article;
use App\Models\Pharmacie\Lot;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockService
{
    public function receive(array $data): Lot
    {
        return DB::transaction(function () use ($data) {
            $lot = Lot::firstOrCreate(
                ['article_id'=>$data['article_id'],'lot_number'=>$data['lot_number']],
                ['expires_at'=>$data['expires_at'] ?? null,'quantity'=>0]
            );
            $lot->quantity  += (int) $data['quantity'];
            $lot->buy_price  = $data['buy_price']  ?? $lot->buy_price;
            $lot->sell_price = $data['sell_price'] ?? $lot->sell_price;
            $lot->supplier   = $data['supplier']   ?? $lot->supplier;
            $lot->expires_at = $data['expires_at'] ?? $lot->expires_at;
            $lot->save();
            return $lot;
        });
    }

    /** Consomme le stock par FIFO et retourne le détail par lots. */
    public function consume(Article $article, int $quantity): array
    {
        if ($quantity <= 0) throw new InvalidArgumentException('quantity must be > 0');

        return DB::transaction(function () use ($article, $quantity) {
            $remaining = $quantity;
            $used = [];
            $lots = $article->lots()->available()->FIFO()->lockForUpdate()->get();

            foreach ($lots as $lot) {
                if ($remaining <= 0) break;
                $take = min($remaining, $lot->quantity);
                if ($take <= 0) continue;

                $lot->quantity -= $take;
                $lot->save();

                $used[] = [
                    'lot_id'     => $lot->id,
                    'lot_number' => $lot->lot_number,
                    'expires_at' => $lot->expires_at?->toDateString(),
                    'taken'      => $take,
                    'unit_price' => (string) ($lot->sell_price ?? $article->sell_price ?? 0),
                ];
                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new InvalidArgumentException("Stock insuffisant pour l'article {$article->id}, manque: {$remaining}");
            }
            return $used;
        });
    }
}
