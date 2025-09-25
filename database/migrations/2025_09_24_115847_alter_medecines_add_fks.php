<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Colonnes manquantes
        Schema::table('medecines', function (Blueprint $table) {
            if (!Schema::hasColumn('medecines', 'visite_id')) {
                $table->uuid('visite_id')->nullable()->after('patient_id'); // UUID comme visites.id
            }
            if (!Schema::hasColumn('medecines', 'service_id')) {
                $table->unsignedBigInteger('service_id')->nullable()->after('visite_id');
            }
            // soignant_id doit pointer vers personnels.id (BIGINT)
            if (!Schema::hasColumn('medecines', 'soignant_id')) {
                $table->unsignedBigInteger('soignant_id')->nullable()->after('service_id');
            }
        });

        // 2) Nettoyage d’anciennes contraintes (si elles existent)
        foreach ([
            'medecines_visite_id_foreign',
            'fk_medecines_visite_id',
            'medecines_service_id_foreign',
            'fk_medecines_service_id',
            'medecines_soignant_id_foreign',
            'fk_medecines_soignant_id',
        ] as $fk) {
            try { DB::statement("ALTER TABLE `medecines` DROP FOREIGN KEY `$fk`"); } catch (\Throwable $e) {}
        }
        // Uniques potentiellement existants
        foreach ([
            'medecines_visite_id_unique',
        ] as $idx) {
            try { Schema::table('medecines', fn(Blueprint $t) => $t->dropUnique($idx)); } catch (\Throwable $e) {}
        }

        // 3) (Re)création des FKs + unique (si tu veux 1 acte par visite)
        Schema::table('medecines', function (Blueprint $table) {
            // FK visite -> visites(id)
            $table->foreign('visite_id', 'fk_medecines_visite_id')
                  ->references('id')->on('visites')
                  ->onDelete('cascade');

            // Un seul enregistrement Médecine par visite ? décommente si OUI :
            $table->unique('visite_id', 'medecines_visite_id_unique');

            // FK service -> services(id) (nullable)
            $table->foreign('service_id', 'fk_medecines_service_id')
                  ->references('id')->on('services')
                  ->nullOnDelete();

            // ⚠️ soignant = personnels.id (pas users)
            $table->foreign('soignant_id', 'fk_medecines_soignant_id')
                  ->references('id')->on('personnels')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('medecines', function (Blueprint $table) {
            try { $table->dropUnique('medecines_visite_id_unique'); } catch (\Throwable $e) {}
            try { $table->dropForeign('fk_medecines_visite_id'); } catch (\Throwable $e) {}
            try { $table->dropForeign('fk_medecines_service_id'); } catch (\Throwable $e) {}
            try { $table->dropForeign('fk_medecines_soignant_id'); } catch (\Throwable $e) {}

            // Optionnel: rollback complet
            // $table->dropColumn(['visite_id','service_id','soignant_id']);
        });
    }
};
