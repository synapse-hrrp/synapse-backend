<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $t) {
            $t->uuid('id')->primary();

            // Références
            $t->uuid('patient_id')->index();
            $t->uuid('visite_id')->nullable()->index();

            // Métadonnées & montants (FR)
            $t->string('numero', 30)->unique()->nullable(); // sera généré côté modèle/contrôleur si tu veux
            $t->string('devise', 3)->default('XOF');
            $t->decimal('remise', 12, 2)->default(0);
            $t->decimal('montant_total', 12, 2)->default(0);
            $t->decimal('montant_paye', 12, 2)->default(0);
            $t->enum('statut_paiement', ['unpaid','partial','paid','canceled'])->default('unpaid');

            // User ayant créé la facture (optionnel)
            $t->unsignedBigInteger('cree_par')->nullable()->index();

            $t->timestamps();
            $t->softDeletes();

            // FKs (patients/visites sont en UUID, users en BIGINT)
            $t->foreign('patient_id')->references('id')->on('patients');
            $t->foreign('visite_id')->references('id')->on('visites');
            $t->foreign('cree_par')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

