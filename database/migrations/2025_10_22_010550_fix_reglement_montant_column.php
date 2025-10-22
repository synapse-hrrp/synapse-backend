<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ⚠️ Nécessite doctrine/dbal
        Schema::table('reglements', function (Blueprint $table) {
            $table->decimal('montant', 12, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        // pas obligatoire, tu peux laisser vide
    }
};
