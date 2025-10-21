<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('personnels', function (Blueprint $table) {
            // Ajoute la colonne deleted_at pour activer les soft deletes
            if (!Schema::hasColumn('personnels', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void {
        Schema::table('personnels', function (Blueprint $table) {
            // Supprime la colonne deleted_at si elle existe
            if (Schema::hasColumn('personnels', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
