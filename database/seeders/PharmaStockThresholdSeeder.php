<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PharmaStockThresholdSeeder extends Seeder
{
    /**
     * Exécute le seeder.
     */
    public function run(): void
    {
        $count = DB::table('pharma_articles')
            ->where(function ($q) {
                $q->whereNull('min_stock')
                  ->orWhere('min_stock', 0)
                  ->orWhereNull('max_stock')
                  ->orWhere('max_stock', 0);
            })
            ->update([
                'min_stock' => DB::raw('IF(min_stock IS NULL OR min_stock = 0, 10, min_stock)'),
                'max_stock' => DB::raw('IF(max_stock IS NULL OR max_stock = 0, 200, max_stock)'),
            ]);

        $this->command->info("✅ Seuils min/max mis à jour sur {$count} article(s).");
    }
}
