<?php
// database/migrations/2025_09_24_120000_enforce_not_null_arus_soignant.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Vérifier qu'il n'y a plus de NULL
        $nulls = DB::table('arus')->whereNull('soignant_id')->count();
        if ($nulls > 0) {
            throw new \RuntimeException("Encore {$nulls} soignant_id NULL: nettoie avant de forcer NOT NULL.");
        }

        // Drop FK existant (quel que soit son nom)
        $fkRows = DB::select("
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'arus'
              AND COLUMN_NAME = 'soignant_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        foreach ($fkRows as $row) {
            $name = $row->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE `arus` DROP FOREIGN KEY `$name`");
        }

        // Forcer NOT NULL + RESTRICT
        DB::statement("ALTER TABLE `arus` MODIFY `soignant_id` BIGINT UNSIGNED NOT NULL");
        Schema::table('arus', function (Blueprint $table) {
            $table->foreign('soignant_id')->references('id')->on('personnels')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Revenir à SET NULL si besoin
        $fkRows = DB::select("
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'arus'
              AND COLUMN_NAME = 'soignant_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        foreach ($fkRows as $row) {
            $name = $row->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE `arus` DROP FOREIGN KEY `$name`");
        }

        DB::statement("ALTER TABLE `arus` MODIFY `soignant_id` BIGINT UNSIGNED NULL");
        Schema::table('arus', function (Blueprint $table) {
            $table->foreign('soignant_id')->references('id')->on('personnels')->nullOnDelete();
        });
    }
};
