<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pharma_carts', function (Blueprint $table) {
            // visites.id est un UUID => on aligne le type
            if (!Schema::hasColumn('pharma_carts', 'visite_id')) {
                $table->foreignUuid('visite_id')
                    ->nullable()
                    ->constrained('visites')
                    ->nullOnDelete()
                    ->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pharma_carts', function (Blueprint $table) {
            if (Schema::hasColumn('pharma_carts', 'visite_id')) {
                $table->dropConstrainedForeignId('visite_id');
            }
        });
    }
};
