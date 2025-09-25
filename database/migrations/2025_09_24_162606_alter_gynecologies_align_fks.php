<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Colonnes manquantes
        Schema::table('gynecologies', function (Blueprint $table) {
            if (!Schema::hasColumn('gynecologies','visite_id')) {
                $table->uuid('visite_id')->nullable()->after('patient_id');
            }
            if (!Schema::hasColumn('gynecologies','service_id')) {
                $table->unsignedBigInteger('service_id')->nullable()->after('visite_id');
            }
            if (!Schema::hasColumn('gynecologies','soignant_id')) {
                $table->unsignedBigInteger('soignant_id')->nullable()->after('service_id');
            }
        });

        // 2) Nettoyage d'anciennes FKs/indices si présents
        foreach ([
            'gynecologies_visite_id_foreign','fk_gynecologies_visite_id',
            'gynecologies_service_id_foreign','fk_gynecologies_service_id',
            'gynecologies_soignant_id_foreign','fk_gynecologies_soignant_id',
        ] as $fk) {
            try { DB::statement("ALTER TABLE `gynecologies` DROP FOREIGN KEY `$fk`"); } catch (\Throwable $e) {}
        }
        foreach (['gynecologies_visite_id_unique'] as $idx) {
            try { Schema::table('gynecologies', fn(Blueprint $t) => $t->dropUnique($idx)); } catch (\Throwable $e) {}
        }

        // 3) Re-créer FKs (et unique visite_id si 1 fiche/visite)
        Schema::table('gynecologies', function (Blueprint $table) {
            $table->foreign('visite_id', 'fk_gynecologies_visite_id')
                  ->references('id')->on('visites')->onDelete('cascade');

            // $table->unique('visite_id', 'gynecologies_visite_id_unique'); // ← décommente si 1 gyneco/visite

            $table->foreign('service_id', 'fk_gynecologies_service_id')
                  ->references('id')->on('services')->nullOnDelete();

            // ⚠️ soignant = PERSONNELS
            $table->foreign('soignant_id', 'fk_gynecologies_soignant_id')
                  ->references('id')->on('personnels')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gynecologies', function (Blueprint $table) {
            try { $table->dropForeign('fk_gynecologies_visite_id'); } catch (\Throwable $e) {}
            try { $table->dropForeign('fk_gynecologies_service_id'); } catch (\Throwable $e) {}
            try { $table->dropForeign('fk_gynecologies_soignant_id'); } catch (\Throwable $e) {}
            // try { $table->dropUnique('gynecologies_visite_id_unique'); } catch (\Throwable $e) {}
            // Optionnel : rollback des colonnes
            // try { $table->dropColumn(['service_id','soignant_id','visite_id']); } catch (\Throwable $e) {}
        });
    }
};
