<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('invoice_id')->index();

            // Détails ligne (FR)
            $t->string('service_slug', 50);        // pansement | laboratoire | consultation ...
            $t->uuid('reference_id')->nullable();  // ID de l’acte si tu veux relier
            $t->string('libelle', 190);
            $t->unsignedInteger('quantite')->default(1);
            $t->decimal('prix_unitaire', 12, 2);
            $t->decimal('total_ligne', 12, 2);

            $t->timestamps();

            // FK vers invoices - la table existe déjà (migration précédente)
            $t->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
