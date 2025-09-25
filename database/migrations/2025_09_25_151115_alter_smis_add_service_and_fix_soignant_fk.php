<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('smis', function (Blueprint $table) {
            // service_id (nullable)
            if (!Schema::hasColumn('smis', 'service_id')) {
                $table->unsignedBigInteger('service_id')->nullable()->after('visite_id');
            }
        });

        // Drop FK soignant -> users si elle existe
        try { DB::statement('ALTER TABLE `smis` DROP FOREIGN KEY `smis_soignant_id_foreign`'); } catch (\Throwable $e) {}

        // (Re)créer les FKs propres
        Schema::table('smis', function (Blueprint $table) {
            // service_id -> services(id)
            if (! $this->hasForeign('smis', 'fk_smis_service_id')) {
                $table->foreign('service_id', 'fk_smis_service_id')
                      ->references('id')->on('services')
                      ->nullOnDelete();
            }

            // soignant_id -> personnels(id)  (si ta colonne est bien de même type/UUID)
            if (Schema::hasColumn('smis','soignant_id') && !$this->hasForeign('smis','fk_smis_soignant_id')) {
                $table->foreign('soignant_id', 'fk_smis_soignant_id')
                      ->references('id')->on('personnels')
                      ->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('smis', function (Blueprint $table) {
            try { $table->dropForeign('fk_smis_service_id'); } catch (\Throwable $e) {}
            try { $table->dropForeign('fk_smis_soignant_id'); } catch (\Throwable $e) {}
            // $table->dropColumn('service_id'); // si tu veux rollback complet
        });
    }

    private function hasForeign(string $table, string $constraint): bool
    {
        try {
            DB::selectOne("
              SELECT CONSTRAINT_NAME
              FROM information_schema.TABLE_CONSTRAINTS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND CONSTRAINT_NAME = ?
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ", [$table, $constraint]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
};
