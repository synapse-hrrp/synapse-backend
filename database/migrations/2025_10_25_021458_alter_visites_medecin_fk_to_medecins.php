<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('visites', function (Blueprint $table) {
            // Supprime l'ancienne contrainte si elle existe (vers users)
            try { $table->dropForeign(['medecin_id']); } catch (\Throwable $e) {}
            // Force nullable et recrée la FK vers medecins
            $table->foreignId('medecin_id')->nullable()->change();
        });

        Schema::table('visites', function (Blueprint $table) {
            $table->foreign('medecin_id')
                  ->references('id')->on('medecins')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visites', function (Blueprint $table) {
            try { $table->dropForeign(['medecin_id']); } catch (\Throwable $e) {}
            $table->foreign('medecin_id')
                  ->references('id')->on('users')
                  ->nullOnDelete();
        });
    }
};
