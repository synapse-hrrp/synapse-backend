<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('invoice_id')->index();

            // Paiement (FR)
            $t->decimal('montant', 12, 2);
            $t->string('devise', 3)->default('XOF');
            $t->enum('methode', ['cash','mobile_money','card','transfer'])->default('cash');
            $t->unsignedBigInteger('recu_par')->nullable()->index();  // user caissier
            $t->timestamp('paye_le')->nullable();

            $t->timestamps();
            $t->softDeletes();

            // FKs
            $t->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $t->foreign('recu_par')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
