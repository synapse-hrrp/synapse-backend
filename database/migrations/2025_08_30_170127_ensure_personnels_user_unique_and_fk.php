<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    private function fkExists(string $table, string $constraint): bool
    {
        $db = DB::getDatabaseName();
        $sql = "SELECT 1
                  FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_NAME = ?
                   AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                 LIMIT 1";
        return (bool) DB::selectOne($sql, [$db, $table, $constraint]);
    }

    private function indexExists(string $table, string $index): bool
    {
        $db = DB::getDatabaseName();
        $sql = "SELECT 1
                  FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND INDEX_NAME = ?
                 LIMIT 1";
        return (bool) DB::selectOne($sql, [$db, $table, $index]);
    }

    public function up(): void
    {
        $fkName     = 'personnels_user_id_foreign';
        $uniqueName = 'personnels_user_id_unique';

        $hasUserIdCol = Schema::hasColumn('personnels', 'user_id');
        $hasFk        = $this->fkExists('personnels', $fkName);
        $hasUnique    = $this->indexExists('personnels', $uniqueName);

        Schema::table('personnels', function (Blueprint $table) use ($hasUserIdCol) {
            // Si besoin, on crée juste la colonne (sans contrainte automatique)
            if (!$hasUserIdCol) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }

            // S'assurer que service_id existe (optionnel)
            if (!Schema::hasColumn('personnels', 'service_id')) {
                $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            }
        });

        // Ajoute la FK seulement si elle n'existe pas déjà
        if (!$hasFk) {
            Schema::table('personnels', function (Blueprint $table) use ($fkName) {
                $table->foreign('user_id', $fkName)
                      ->references('id')->on('users')
                      ->cascadeOnDelete();
            });
        }

        // Ajoute l’unique seulement s’il n’existe pas déjà
        if (!$hasUnique) {
            Schema::table('personnels', function (Blueprint $table) use ($uniqueName) {
                $table->unique('user_id', $uniqueName);
            });
        }
    }

    public function down(): void
    {
        // rollback minimal et sûr (on ne casse pas la FK si tu l’avais déjà avant)
        try {
            Schema::table('personnels', function (Blueprint $table) {
                $table->dropUnique('personnels_user_id_unique');
            });
        } catch (\Throwable $e) {}
    }
};
