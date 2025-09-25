<?php
// database/migrations/2025_09_20_064457_hardening_arus_visite_fk_unique.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function fkExists(string $table, string $fkName): bool
    {
        $db = DB::getDatabaseName();
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $fkName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $db = DB::getDatabaseName();
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }

    public function up(): void
    {
        // 1) Ajouter les colonnes si besoin (séparé des contraintes)
        Schema::table('arus', function (Blueprint $t) {
            if (!Schema::hasColumn('arus', 'service_id')) {
                $t->foreignId('service_id')->nullable()->after('patient_id');
            }
            if (!Schema::hasColumn('arus', 'visite_id')) {
                $t->uuid('visite_id')->after('patient_id');
            }
        });

        // 2) Poser la FK service_id → services(id) si absente
        if (!$this->fkExists('arus', 'arus_service_id_foreign') && Schema::hasColumn('arus', 'service_id')) {
            Schema::table('arus', function (Blueprint $t) {
                // nommer explicitement pour éviter les collisions
                $t->foreign('service_id', 'arus_service_id_foreign')
                  ->references('id')->on('services')->nullOnDelete();
            });
        }

        // 3) Poser la FK visite_id → visites(id) si absente
        if (!$this->fkExists('arus', 'arus_visite_id_foreign') && Schema::hasColumn('arus', 'visite_id')) {
            Schema::table('arus', function (Blueprint $t) {
                $t->foreign('visite_id', 'arus_visite_id_foreign')
                  ->references('id')->on('visites')->cascadeOnDelete();
            });
        }

        // 4) Unicité stricte 1→1 (unique) si absente
        if (!$this->indexExists('arus', 'arus_visite_id_unique') && Schema::hasColumn('arus', 'visite_id')) {
            Schema::table('arus', function (Blueprint $t) {
                $t->unique('visite_id', 'arus_visite_id_unique');
            });
        }

        // 5) Index service_id si absent (souvent déjà créé via la FK, mais on vérifie)
        if (!$this->indexExists('arus', 'arus_service_id_index') && Schema::hasColumn('arus', 'service_id')) {
            Schema::table('arus', function (Blueprint $t) {
                $t->index('service_id', 'arus_service_id_index');
            });
        }
    }

    public function down(): void
    {
        // drop unique si présent
        if ($this->indexExists('arus', 'arus_visite_id_unique')) {
            Schema::table('arus', function (Blueprint $t) {
                $t->dropUnique('arus_visite_id_unique');
            });
        }

        // drop FK visite si présente
        if ($this->fkExists('arus', 'arus_visite_id_foreign')) {
            Schema::table('arus', function (Blueprint $t) {
                $t->dropForeign('arus_visite_id_foreign');
            });
        }

        // drop index service si présent
        if ($this->indexExists('arus', 'arus_service_id_index')) {
            Schema::table('arus', function (Blueprint $t) {
                $t->dropIndex('arus_service_id_index');
            });
        }

        // drop FK service si présente
        if ($this->fkExists('arus', 'arus_service_id_foreign')) {
            Schema::table('arus', function (Blueprint $t) {
                $t->dropForeign('arus_service_id_foreign');
            });
        }

        // (optionnel) drop colonnes si tu veux revenir totalement en arrière
        Schema::table('arus', function (Blueprint $t) {
            if (Schema::hasColumn('arus', 'visite_id')) {
                $t->dropColumn('visite_id');
            }
            if (Schema::hasColumn('arus', 'service_id')) {
                $t->dropColumn('service_id');
            }
        });
    }
};
