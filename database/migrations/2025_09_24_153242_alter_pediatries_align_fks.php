<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Colonnes manquantes
        Schema::table('pediatries', function (Blueprint $table) {
            if (!Schema::hasColumn('pediatries','visite_id')) {
                $table->uuid('visite_id')->nullable()->after('patient_id');
            }
            if (!Schema::hasColumn('pediatries','service_id')) {
                $table->unsignedBigInteger('service_id')->nullable()->after('visite_id');
            }
            if (!Schema::hasColumn('pediatries','soignant_id')) {
                $table->unsignedBigInteger('soignant_id')->nullable()->after('service_id');
            }
        });

        // 2) Purge des anciennes FKs/indices si existants
        foreach ([
            'pediatries_visite_id_foreign','fk_pediatries_visite_id',
            'pediatries_service_id_foreign','fk_pediatries_service_id',
            'pediatries_soignant_id_foreign','fk_pediatries_soignant_id',
        ] as $fk) {
            try { DB::statement("ALTER TABLE `pediatries` DROP FOREIGN KEY `$fk`"); } catch (\Throwable $e) {}
        }
        foreach (['pediatries_visite_id_unique'] as $idx) {
            try { Schema::table('pediatries', fn(Blueprint $t) => $t->dropUnique($idx)); } catch (\Throwable $e) {}
        }

        // 3) Re-créer les FKs (et unique visite_id si 1 seule fiche/visite)
        Schema::table('pediatries', function (Blueprint $table) {
            $table->foreign('visite_id', 'fk_pediatries_visite_id')
                  ->references('id')->on('visites')->onDelete('cascade');

            // $table->unique('visite_id', 'pediatries_visite_id_unique'); // ← décommente si 1 pediatrie/visite

            $table->foreign('service_id', 'fk_pediatries_service_id')
                  ->references('id')->on('services')->nullOnDelete();

            // ⚠️ soignant = PERSONNELS
            $table->foreign('soignant_id', 'fk_pediatries_soignant_id')
                  ->references('id')->on('personnels')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pediatries', function (Blueprint $table) {
            try { $table->dropForeign('fk_pediatries_visite_id'); } catch (\Throwable $e) {}
            try { $table->dropForeign('fk_pediatries_service_id'); } catch (\Throwable $e) {}
            try { $table->dropForeign('fk_pediatries_soignant_id'); } catch (\Throwable $e) {}
            // try { $table->dropUnique('pediatries_visite_id_unique'); } catch (\Throwable $e) {}
            // Optionnel : rollback complet
            // try { $table->dropColumn(['service_id','soignant_id','visite_id']); } catch (\Throwable $e) {}
        });
    }
};
