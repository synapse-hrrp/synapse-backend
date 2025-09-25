<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Ajouter colonnes manquantes
        Schema::table('pansements', function (Blueprint $table) {
            if (!Schema::hasColumn('pansements','visite_id')) {
                $table->uuid('visite_id')->nullable()->after('patient_id');
            }
            if (!Schema::hasColumn('pansements','service_id')) {
                $table->unsignedBigInteger('service_id')->nullable()->after('visite_id');
            }
            if (!Schema::hasColumn('pansements','soignant_id')) {
                $table->unsignedBigInteger('soignant_id')->nullable()->after('service_id');
            }
        });

        // 2) Nettoyage d’anciennes FKs/indices si présents
        foreach ([
            'pansements_visite_id_foreign',
            'fk_pansements_visite_id',
            'pansements_service_id_foreign',
            'fk_pansements_service_id',
            'pansements_soignant_id_foreign',
            'fk_pansements_soignant_id',
        ] as $fk) {
            try { DB::statement("ALTER TABLE `pansements` DROP FOREIGN KEY `$fk`"); } catch (\Throwable $e) {}
        }
        foreach (['pansements_visite_id_unique'] as $idx) {
            try { Schema::table('pansements', fn(Blueprint $t) => $t->dropUnique($idx)); } catch (\Throwable $e) {}
        }

        // 3) Re-créer FKs (et unique visite_id si tu veux 1 acte/visite)
        Schema::table('pansements', function (Blueprint $table) {
            $table->foreign('visite_id', 'fk_pansements_visite_id')
                  ->references('id')->on('visites')->onDelete('cascade');

            // Un seul pansement par visite ? Décommente si OUI :
            // $table->unique('visite_id', 'pansements_visite_id_unique');

            $table->foreign('service_id', 'fk_pansements_service_id')
                  ->references('id')->on('services')->nullOnDelete();

            // ⚠️ soignant = PERSONNELS (pas users)
            $table->foreign('soignant_id', 'fk_pansements_soignant_id')
                  ->references('id')->on('personnels')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pansements', function (Blueprint $table) {
            try { $table->dropForeign('fk_pansements_visite_id'); } catch (\Throwable $e) {}
            try { $table->dropForeign('fk_pansements_service_id'); } catch (\Throwable $e) {}
            try { $table->dropForeign('fk_pansements_soignant_id'); } catch (\Throwable $e) {}
            // try { $table->dropUnique('pansements_visite_id_unique'); } catch (\Throwable $e) {}
            // Optionnel: rollback complet
            // try { $table->dropColumn(['service_id','soignant_id','visite_id']); } catch (\Throwable $e) {}
        });
    }
};
