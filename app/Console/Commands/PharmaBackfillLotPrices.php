<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Artisan command:
 *  - Remplit (ou met à jour) buy_price / sell_price des lots depuis les prix de l'article
 *  - Options:
 *      --dry-run   : ne fait qu'afficher ce qui serait modifié
 *      --backup    : crée une table de backup des lots avant modification
 *      --article=  : limite aux lots d'un article_id donné (int)
 *      --force     : écrase même les prix déjà renseignés
 *      --only-null : (par défaut) ne modifie que les lots dont buy/sell sont NULL
 *
 * Exemples:
 *  php artisan pharma:backfill-lot-prices --dry-run
 *  php artisan pharma:backfill-lot-prices --backup
 *  php artisan pharma:backfill-lot-prices --article=12
 *  php artisan pharma:backfill-lot-prices --force
 */
class PharmaBackfillLotPrices extends Command
{
    protected $signature = 'pharma:backfill-lot-prices
                            {--dry-run : Affiche ce qui serait fait sans écrire}
                            {--backup : Crée une table de sauvegarde des lots avant mise à jour}
                            {--article= : Limite au seul article_id (int)}
                            {--force : Écrase même les prix déjà renseignés}
                            {--only-null : (par défaut) Ne modifie que les lots dont buy/sell sont NULL}';

    protected $description = 'Renseigne buy_price / sell_price des lots à partir des prix de l’article (avec options dry-run, backup, scope)';

    public function handle(): int
    {
        $dryRun    = (bool) $this->option('dry-run');
        $doBackup  = (bool) $this->option('backup');
        $force     = (bool) $this->option('force');
        $onlyNull  = $this->option('only-null') || ! $force; // par défaut true si pas --force
        $articleId = $this->option('article');

        if ($force && $onlyNull) {
            $this->warn('Option --force fournie : --only-null est ignoré.');
            $onlyNull = false;
        }

        // WHERE dynamique
        $wheres = [];
        if ($onlyNull) {
            $wheres[] = '(l.buy_price IS NULL OR l.sell_price IS NULL)';
        }
        if ($articleId !== null && $articleId !== '') {
            if (!ctype_digit((string) $articleId)) {
                $this->error('--article doit être un entier (id de l’article).');
                return self::INVALID;
            }
            $wheres[] = 'l.article_id = ' . (int) $articleId;
        }
        $whereSql = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

        // 1) Preview
        $previewSql = "
            SELECT l.id, l.article_id, a.code, a.name,
                   l.buy_price   AS lot_buy_before,
                   a.buy_price   AS art_buy,
                   l.sell_price  AS lot_sell_before,
                   a.sell_price  AS art_sell
            FROM pharma_lots l
            JOIN pharma_articles a ON a.id = l.article_id
            {$whereSql}
            ORDER BY l.id ASC
        ";

        $rows  = DB::select($previewSql);
        $count = count($rows);

        if ($count === 0) {
            $this->info('Aucun lot à mettre à jour selon les critères. ✅');
            return self::SUCCESS;
        }

        $this->line("Lots concernés : {$count}");
        foreach (array_slice($rows, 0, 20) as $r) {
            $this->line(sprintf(
                "  - lot #%d (art %d %s): buy %s -> %s | sell %s -> %s",
                $r->id, $r->article_id, $r->code ?? '',
                $r->lot_buy_before ?? 'NULL',  $r->art_buy ?? 'NULL',
                $r->lot_sell_before ?? 'NULL', $r->art_sell ?? 'NULL'
            ));
        }
        if ($count > 20) {
            $this->line('  … (affichage limité à 20 lignes)');
        }

        if ($dryRun) {
            $this->info('Dry-run terminé. Aucune écriture effectuée. 🧪');
            return self::SUCCESS;
        }

        // 2) Backup (facultatif)
        if ($doBackup) {
            $backupName = 'backup_pharma_lots_' . now()->format('Ymd_His') . '_' . Str::random(4);
            $this->warn("Création de la table de sauvegarde: {$backupName}");

            // DDL => hors transaction; puis copie
            DB::statement("CREATE TABLE `{$backupName}` LIKE pharma_lots");
            DB::statement("INSERT INTO `{$backupName}` SELECT * FROM pharma_lots");
            $this->info("Backup OK → table `{$backupName}`");
        }

        // 3) Update
        $updateSql = $force
            ? "
                UPDATE pharma_lots l
                JOIN pharma_articles a ON a.id = l.article_id
                SET l.buy_price  = a.buy_price,
                    l.sell_price = a.sell_price
                {$whereSql}
              "
            : "
                UPDATE pharma_lots l
                JOIN pharma_articles a ON a.id = l.article_id
                SET l.buy_price  = COALESCE(l.buy_price,  a.buy_price),
                    l.sell_price = COALESCE(l.sell_price, a.sell_price)
                {$whereSql}
              ";

        $affected = 0;
        DB::transaction(function () use ($updateSql, &$affected) {
            $affected = DB::update($updateSql);
        });

        $this->info("Mise à jour effectuée. Lignes modifiées : {$affected} ✅");

        // 4) Contrôle post-op
        $postCheck = DB::selectOne("
            SELECT COUNT(*) AS still_null
            FROM pharma_lots l
            WHERE l.buy_price IS NULL OR l.sell_price IS NULL
        ");
        $stillNull = (int) ($postCheck->still_null ?? 0);

        if ($stillNull > 0) {
            $this->warn("Attention : {$stillNull} lot(s) ont encore un prix NULL (prix article NULL ?)");
        } else {
            $this->info('Tous les lots ont maintenant buy/sell renseignés. 🎉');
        }

        return self::SUCCESS;
    }
}
