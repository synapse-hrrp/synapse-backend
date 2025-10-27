<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('pharma_lots', function (Blueprint $t) {
            $t->unique(['article_id','lot_number'], 'pharma_lots_article_lot_unique');
        });
    }

    public function down(): void {
        Schema::table('pharma_lots', function (Blueprint $t) {
            $t->dropUnique('pharma_lots_article_lot_unique');
        });
    }
};
