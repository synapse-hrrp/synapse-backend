<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('medecines', function (Blueprint $table) {
            // Ajouter la colonne si elle n’existe pas déjà
            if (!Schema::hasColumn('medecines', 'service_id')) {
                $table->unsignedBigInteger('service_id')->nullable()->after('visite_id');
            }
        });

        // Ajout de la contrainte FK (séparé pour éviter les erreurs)
        Schema::table('medecines', function (Blueprint $table) {
            // D’abord on supprime une éventuelle contrainte précédente
            try { $table->dropForeign('fk_medecines_service_id'); } catch (\Throwable $e) {}

            // Ensuite on crée la nouvelle FK
            $table->foreign('service_id', 'fk_medecines_service_id')
                  ->references('id')->on('services')
                  ->nullOnDelete(); // si un service est supprimé => service_id = NULL
        });
    }

    public function down(): void
    {
        Schema::table('medecines', function (Blueprint $table) {
            try { $table->dropForeign('fk_medecines_service_id'); } catch (\Throwable $e) {}
            try { $table->dropColumn('service_id'); } catch (\Throwable $e) {}
        });
    }
};
