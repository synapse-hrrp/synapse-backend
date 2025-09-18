<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facture_lignes', function (Blueprint $table) {
            $table->id();
            $table->uuid('facture_id');
            $table->uuid('tarif_id')->nullable();          // optionnel si basé sur un tarif

            $table->string('designation');
            $table->decimal('quantite', 8, 2)->default(1);
            $table->decimal('prix_unitaire', 12, 2);
            $table->decimal('montant', 12, 2);

            $table->timestamps();

            // Index
            $table->index(['facture_id']);
            $table->index(['tarif_id']);

            // FKs
            $table->foreign('facture_id')->references('id')->on('factures')->cascadeOnDelete();
            $table->foreign('tarif_id')->references('id')->on('tarifs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facture_lignes');
    }
};
