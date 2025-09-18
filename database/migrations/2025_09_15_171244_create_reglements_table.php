<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reglements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('facture_id');

            $table->decimal('montant', 12, 2);
            $table->string('devise', 3)->default('CDF');
            $table->string('mode')->nullable();             // CASH | MOMO | CARTE …
            $table->string('reference')->nullable();        // N° transaction / reçu

            $table->timestamps();

            // Index
            $table->index(['facture_id']);
            $table->index(['mode']);
            $table->index(['created_at']);

            // FK
            $table->foreign('facture_id')->references('id')->on('factures')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglements');
    }
};
