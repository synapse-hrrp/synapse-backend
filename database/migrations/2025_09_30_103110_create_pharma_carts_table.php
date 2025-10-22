<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pharma_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open'); // open | checked_out | cancelled

            // Informations client/patient optionnelles
            $table->uuid('patient_id')->nullable();
            $table->string('customer_name')->nullable();

            // Totaux
            $table->decimal('total_ht', 12, 2)->default(0);
            $table->decimal('total_tva', 12, 2)->default(0);
            $table->decimal('total_ttc', 12, 2)->default(0);
            $table->string('currency', 10)->default('XAF');

            $table->timestamps();
            $table->index(['status']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('pharma_carts');
    }
};
