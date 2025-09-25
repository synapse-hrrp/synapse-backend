<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Colonnes manquantes
        Schema::table('arus', function (Blueprint $table) {
            if (! Schema::hasColumn('arus', 'visite_id')) {
                // UUID pour matcher visites.id (CHAR(36))
                $table->uuid('visite_id')->nullable()->after('patient_id');
            }

            if (! Schema::hasColumn('arus', 'service_id')) {
                // On ajoute d'abord la colonne, la contrainte viendra après (pour éviter les collisions)
                $table->unsignedBigInteger('service_id')->nullable()->after('visite_id');
            }
        });

        // 2) Nettoyage des contraintes/indices existants (évite l'erreur 1826)
        //    Nom Laravel par défaut: arus_visite_id_foreign / arus_visite_id_unique
        try {
            DB::statement('ALTER TABLE `arus` DROP FOREIGN KEY `arus_visite_id_foreign`');
        } catch (\Throwable $e) { /* ignore */ }

        // L’index unique par défaut s’appelle arus_visite_id_unique
        try {
            Schema::table('arus', function (Blueprint $table) {
                $table->dropUnique('arus_visite_id_unique');
            });
        } catch (\Throwable $e) { /* ignore */ }

        // Même traitement pour service_id si une FK existait déjà
        try {
            DB::statement('ALTER TABLE `arus` DROP FOREIGN KEY `arus_service_id_foreign`');
        } catch (\Throwable $e) { /* ignore */ }

        // 3) (Re)création des FKs + unique avec des noms explicites (pour éviter les collisions futures)
        Schema::table('arus', function (Blueprint $table) {
            // FK visite (UUID) -> visites(id), cascade on delete
            $table->foreign('visite_id', 'fk_arus_visite_id')
                  ->references('id')->on('visites')
                  ->onDelete('cascade');

            // Un seul ARU par visite (si c'est bien ce que tu veux)
            $table->unique('visite_id', 'arus_visite_id_unique');

            // FK service (nullable) -> services(id), NULL on delete
            // (si tu es sûr que services.id existe en BIGINT)
            $table->foreign('service_id', 'fk_arus_service_id')
                  ->references('id')->on('services')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // On supprime d'abord les contraintes, puis éventuellement les colonnes
        Schema::table('arus', function (Blueprint $table) {
            // Drop unique
            try { $table->dropUnique('arus_visite_id_unique'); } catch (\Throwable $e) {}

            // Drop FKs (avec les noms explicites utilisés en up())
            try { $table->dropForeign('fk_arus_visite_id'); } catch (\Throwable $e) {}
            try { $table->dropForeign('fk_arus_service_id'); } catch (\Throwable $e) {}

            // Si tu veux un rollback complet, décommente la ligne suivante
            // $table->dropColumn(['visite_id', 'service_id']);
        });
    }
};
