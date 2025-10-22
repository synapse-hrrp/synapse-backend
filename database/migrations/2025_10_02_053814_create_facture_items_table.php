<?php

// database/migrations/2025_10_01_000000_create_facture_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facture_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Contexte obligatoire pour créer/attacher une facture automatiquement
            $table->uuid('patient_id');            // utile si on doit créer la facture
            $table->uuid('facture_id')->nullable();// peut être null à la création, si on laisse le service la créer

            // Tarification (optionnelle)
            $table->uuid('tarif_id')->nullable();
            $table->string('tarif_code', 50)->nullable();

            // Saisie caisse
            $table->string('designation', 255);       // ex: "Consultation", "Radio Thorax", "Acompte"
            $table->integer('quantite')->default(1);
            $table->decimal('prix_unitaire', 12, 2);
            $table->decimal('total', 12, 2);      // = quantite * prix_unitaire - remise
            $table->decimal('remise', 12, 2)->nullable();
            $table->string('devise', 3)->default('XAF');

            $table->enum('type_origine', ['tarif','manuel'])->default('manuel');
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('facture_id')->references('id')->on('factures')->nullOnDelete();
            $table->foreign('tarif_id')->references('id')->on('tarifs')->nullOnDelete();

            // Index utiles
            $table->index(['patient_id', 'facture_id']);
            $table->index(['tarif_id', 'tarif_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facture_items');
    }
};
