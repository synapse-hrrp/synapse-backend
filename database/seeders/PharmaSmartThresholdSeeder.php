<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PharmaSmartThresholdSeeder extends Seeder
{
    /**
     * Heuristique pro :
     * - recent_out_7  : quantités sorties (type='out') sur 7 jours
     * - recent_out_30 : quantités sorties (type='out') sur 30 jours
     * - on_hand       : stock total (somme des lots)
     * 
     * min_stock :
     *   - vise ~2 semaines de couverture = recent_out_7 * 2
     *   - ou ~1 semaine moyenne = recent_out_30 / 4
     *   - fallback si pas d’historique : 10% du stock courant (ou 1 pack)
     *   - clamp entre 5 et 500
     *   - arrondi au multiple de pack_size (>= pack_size si pack_size > 0)
     * 
     * max_stock :
     *   - 3 × min_stock au minimum
     *   - ou min_stock + recent_out_30 (couverture d’1 mois au total)
     *   - clamp à 5000
     *   - arrondi au multiple de pack_size
     */
    public function run(): void
    {
        // Récupère les métriques en une seule requête par article
        $rows = DB::select("
            SELECT
                a.id,
                COALESCE(a.pack_size, 1)            AS pack_size,
                COALESCE(a.min_stock, 0)            AS min_stock_old,
                COALESCE(a.max_stock, 0)            AS max_stock_old,
                COALESCE(SUM(l.quantity), 0)        AS on_hand,
                COALESCE((
                    SELECT SUM(m.quantity)
                    FROM pharma_stock_movements m
                    WHERE m.article_id = a.id AND m.type = 'out'
                      AND m.created_at >= NOW() - INTERVAL 7 DAY
                ), 0) AS recent_out_7,
                COALESCE((
                    SELECT SUM(m.quantity)
                    FROM pharma_stock_movements m
                    WHERE m.article_id = a.id AND m.type = 'out'
                      AND m.created_at >= NOW() - INTERVAL 30 DAY
                ), 0) AS recent_out_30
            FROM pharma_articles a
            LEFT JOIN pharma_lots l ON l.article_id = a.id
            GROUP BY a.id, a.pack_size, a.min_stock, a.max_stock
        ");

        $updated = 0;

        foreach ($rows as $r) {
            $pack = max(1, (int)$r->pack_size);
            $onHand = (int)$r->on_hand;
            $out7   = (int)$r->recent_out_7;
            $out30  = (int)$r->recent_out_30;

            // Couverture cible (base) selon l’historique
            $baseA = (int)ceil($out7 * 2);     // ~2 semaines
            $baseB = (int)ceil($out30 / 4);    // ~1 semaine moyenne
            $base  = max($baseA, $baseB);

            // Fallback si pas ou peu d’historique
            $fallback = max((int)ceil($onHand * 0.10), $pack);

            // min stock brut
            $minRaw = max($base, $fallback);

            // clamp min
            $minClamped = max(5, min(500, $minRaw));

            // arrondi à un multiple de pack
            $minStock = $this->roundUpToPack($minClamped, $pack);

            // max stock : 3× min ou min + ventes 30j
            $maxRaw = max($minStock * 3, $minStock + $out30);

            // clamp + arrondi
            $maxClamped = max(20, min(5000, $maxRaw));
            $maxStock   = $this->roundUpToPack($maxClamped, $pack);

            // Évite des incohérences (max < min)
            if ($maxStock < $minStock) {
                $maxStock = $this->roundUpToPack($minStock * 3, $pack);
            }

            // Ne fais l’UPDATE que si ça change
            if ((int)$r->min_stock_old !== $minStock || (int)$r->max_stock_old !== $maxStock) {
                DB::table('pharma_articles')
                    ->where('id', $r->id)
                    ->update([
                        'min_stock' => $minStock,
                        'max_stock' => $maxStock,
                        'updated_at'=> now(),
                    ]);
                $updated++;
            }
        }

        $this->command->info("✅ Seuils min/max recalculés intelligemment pour {$updated} article(s).");
    }

    private function roundUpToPack(int $qty, int $pack): int
    {
        if ($pack <= 1) return max(1, $qty);
        $mult = (int)ceil($qty / $pack);
        return max($pack, $mult * $pack);
    }
}
