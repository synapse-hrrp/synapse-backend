<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharma_carts', function (Blueprint $table) {
            // Ajout du champ visite_id (UUID si tes IDs sont UUID)
            if (!Schema::hasColumn('pharma_carts', 'visite_id')) {
                $table->uuid('visite_id')->nullable()->after('status');

                // Clé étrangère optionnelle vers la table visites
                $table->foreign('visite_id')
                      ->references('id')
                      ->on('visites')
                      ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pharma_carts', function (Blueprint $table) {
            if (Schema::hasColumn('pharma_carts', 'visite_id')) {
                $table->dropForeign(['visite_id']);
                $table->dropColumn('visite_id');
            }
        });
    }
};
