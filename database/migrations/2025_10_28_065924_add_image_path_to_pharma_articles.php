<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('pharma_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('pharma_articles', 'image_path')) {
                // l'emplacement exact ("after") n'a pas d'importance fonctionnelle
                $table->string('image_path')->nullable()->after('tax_rate');
            }
        });
    }

    public function down(): void {
        Schema::table('pharma_articles', function (Blueprint $table) {
            if (Schema::hasColumn('pharma_articles', 'image_path')) {
                $table->dropColumn('image_path');
            }
        });
    }
};
