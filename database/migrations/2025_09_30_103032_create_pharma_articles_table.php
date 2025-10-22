<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pharma_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dci_id')->nullable()->constrained('dcis')->nullOnDelete();

            $table->string('name');
            $table->string('code')->unique();        // SKU
            $table->string('form')->nullable();      // comprimé, sirop...
            $table->string('dosage')->nullable();    // 500 mg, 250 mg/5ml...
            $table->string('unit')->nullable();      // mg, ml, UI...
            $table->unsignedInteger('pack_size')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('min_stock')->default(0);
            $table->unsignedInteger('max_stock')->default(0);

            // Prix par défaut (peuvent être surchargés par lot)
            $table->decimal('buy_price', 12, 2)->nullable();
            $table->decimal('sell_price', 12, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);

            $table->timestamps();

            $table->index(['dci_id','is_active']);
            $table->index(['name']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('pharma_articles');
    }
};
