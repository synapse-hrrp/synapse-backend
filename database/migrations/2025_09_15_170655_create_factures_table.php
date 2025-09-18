<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('factures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('numero')->unique();             // FAC-YYYY-000001

            $table->uuid('visite_id')->nullable();
            $table->uuid('patient_id')->nullable();

            $table->decimal('montant_total', 12, 2)->default(0);
            $table->decimal('montant_du', 12, 2)->default(0);
            $table->string('devise', 3)->default('CDF');
            $table->string('statut')->default('IMPAYEE');   // IMPAYEE | PARTIELLE | PAYEE | ANNULEE

            $table->timestamps();

            // Index
            $table->index(['patient_id']);
            $table->index(['visite_id']);
            $table->index(['statut']);
            $table->index(['created_at']);

            // FKs (nullOnDelete sur patient/visite si on autorise suppression logique)
            $table->foreign('visite_id')->references('id')->on('visites')->nullOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};
