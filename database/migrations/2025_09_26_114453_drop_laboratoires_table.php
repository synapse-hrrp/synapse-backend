<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si tu n’en as plus besoin et qu’il n’y a pas de data à conserver
        Schema::dropIfExists('laboratoires');
    }

    public function down(): void
    {
        // Optionnel : recréer la table minimale si tu fais un rollback
        Schema::create('laboratoires', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Mets ici les colonnes minimales si tu veux pouvoir rollback
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
