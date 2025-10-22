<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pharma_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('pharma_articles')->cascadeOnDelete();

            $table->string('lot_number');
            $table->date('expires_at')->nullable();
            $table->integer('quantity')->default(0);

            // Prix spécifiques à ce lot
            $table->decimal('buy_price', 12, 2)->nullable();
            $table->decimal('sell_price', 12, 2)->nullable();

            $table->string('supplier')->nullable();
            $table->timestamps();

            $table->unique(['article_id','lot_number']);
            $table->index(['article_id','expires_at']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('pharma_lots');
    }
};
