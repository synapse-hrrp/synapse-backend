<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PharmaBackfillArticleThresholds extends Command
{
    protected $signature = 'pharma:backfill-article-thresholds
                            {--min=10 : Valeur par défaut pour min_stock}
                            {--max=100 : Valeur par défaut pour max_stock}
                            {--dry-run : Affiche ce qui serait modifié sans écrire}
                            {--reset : Réinitialise tous les articles (même ceux déjà renseignés)}';

    protected $description = 'Corrige ou réinitialise les min_stock / max_stock des articles (avec options min, max, dry-run, reset)';

    public function handle(): int
    {
        $min   = (int) $this->option('min');
        $max   = (int) $this->option('max');
        $dry   = (bool) $this->option('dry-run');
        $reset = (bool) $this->option('reset');

        $this->line("🔧 Paramètres : min={$min} | max={$max} | reset=" . ($reset ? 'OUI' : 'non') . " | dry-run=" . ($dry ? 'OUI' : 'non'));

        // Construire la requête de base
        $query = DB::table('pharma_articles');

        if ($reset) {
            $rows = $query->select('id', 'name', 'min_stock', 'max_stock')->get();
        } else {
            $rows = $query->select('id', 'name', 'min_stock', 'max_stock')
                ->where(function ($q) {
                    $q->whereNull('min_stock')
                      ->orWhere('min_stock', 0)
                      ->orWhereNull('max_stock')
                      ->orWhere('max_stock', 0);
                })
                ->get();
        }

        $count = $rows->count();

        if ($count === 0) {
            $this->info('✅ Aucun article à corriger : tout est déjà conforme.');
            return self::SUCCESS;
        }

        $this->line("Articles concernés : {$count}");
        foreach ($rows->take(10) as $r) {
            $this->line(sprintf(
                "- #%d %s : min=%s, max=%s",
                $r->id,
                $r->name,
                $r->min_stock ?? 'NULL',
                $r->max_stock ?? 'NULL'
            ));
        }
        if ($count > 10) $this->line("... (affichage limité à 10 lignes)");

        if ($dry) {
            $this->info('🧪 Mode dry-run : aucune écriture effectuée.');
            return self::SUCCESS;
        }

        // Appliquer les mises à jour
        if ($reset) {
            $affected = DB::table('pharma_articles')->update([
                'min_stock' => $min,
                'max_stock' => $max,
            ]);
            $this->info("♻️  Réinitialisation complète : {$affected} articles mis à jour ✅");
        } else {
            $affected = 0;

            $affected += DB::table('pharma_articles')
                ->where(function ($q) {
                    $q->whereNull('min_stock')->orWhere('min_stock', 0);
                })
                ->update(['min_stock' => $min]);

            $affected += DB::table('pharma_articles')
                ->where(function ($q) {
                    $q->whereNull('max_stock')->orWhere('max_stock', 0);
                })
                ->update(['max_stock' => $max]);

            $this->info("✅ Mise à jour terminée : {$affected} enregistrements corrigés.");
        }

        return self::SUCCESS;
    }
}
