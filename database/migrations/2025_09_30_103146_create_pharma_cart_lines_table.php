<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pharma_cart_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('pharma_carts')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('pharma_articles')->cascadeOnDelete();
            // on ne fixe pas le lot ici (FIFO au checkout)
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->nullable(); // sinon article.sell_price
            $table->decimal('tax_rate', 5, 2)->nullable();     // sinon article.tax_rate

            // calculs mémorisés (confort)
            $table->decimal('line_ht', 12, 2)->default(0);
            $table->decimal('line_tva', 12, 2)->default(0);
            $table->decimal('line_ttc', 12, 2)->default(0);

            $table->timestamps();

            $table->unique(['cart_id','article_id']); // 1 ligne/article => on merge les quantités
        });
    }
    public function down(): void {
        Schema::dropIfExists('pharma_cart_lines');
    }
};
