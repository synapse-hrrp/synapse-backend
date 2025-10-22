<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('examens', function (Blueprint $table) {
            // Supprime l’ancienne FK si elle existait (vers personnels)
            try { $table->dropForeign(['demande_par']); } catch (\Throwable $e) {}

            // ⚠️ On suppose que 'demande_par' est déjà de type UUID et NOT NULL.
            // Si à adapter : installer doctrine/dbal et faire un ->change().

            // Nouvelle FK : demande_par → medecins.id
            $table->foreign('demande_par')
                  ->references('id')->on('medecins')
                  ->cascadeOnUpdate();
                  // ->restrictOnDelete(); // ou ->nullOnDelete() si tu rends la colonne nullable
        });
    }

    public function down(): void
    {
        Schema::table('examens', function (Blueprint $table) {
            try { $table->dropForeign(['demande_par']); } catch (\Throwable $e) {}
            // Retour à l’ancienne FK si besoin :
            $table->foreign('demande_par')
                  ->references('id')->on('personnels')
                  ->cascadeOnUpdate();
        });
    }
};
