<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pharma_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('pharma_articles')->cascadeOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('pharma_lots')->nullOnDelete();

            $table->string('type'); // in | out
            $table->integer('quantity'); // > 0

            $table->decimal('unit_price', 12, 2)->nullable(); // achat ou vente
            $table->string('reason')->nullable();   // reception, sale, transfer, waste...
            $table->string('reference')->nullable();// ex: n° facture

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['article_id','type']);
            $table->index(['lot_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('pharma_stock_movements');
    }
};
