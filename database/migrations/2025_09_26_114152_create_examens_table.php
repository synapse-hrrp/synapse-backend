<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examens', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Liens
            $table->uuid('patient_id');                 // à aligner avec patients.id si besoin
            $table->string('service_slug')->nullable(); // <- remplace service_id (référence métier)
            $table->unsignedBigInteger('demande_par')->nullable(); // personnels.id
            $table->unsignedBigInteger('valide_par')->nullable();  // personnels.id

            // Origine de la demande
            $table->enum('type_origine', ['interne','externe'])->default('externe');
            $table->string('prescripteur_externe')->nullable();
            $table->string('reference_demande')->nullable();

            // Données examen
            $table->string('code_examen');
            $table->string('nom_examen');
            $table->string('prelevement')->nullable();

            $table->enum('statut', ['en_attente','en_cours','termine','valide'])->default('en_attente');

            // Résultats
            $table->string('valeur_resultat')->nullable();
            $table->string('unite')->nullable();
            $table->string('intervalle_reference')->nullable();
            $table->json('resultat_json')->nullable();

            // Facturation
            $table->decimal('prix', 10, 2)->nullable();
            $table->string('devise', 10)->nullable();
            $table->uuid('facture_id')->nullable();

            // Dates
            $table->timestamp('date_demande')->nullable();
            $table->timestamp('date_validation')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Index utiles
            $table->index('patient_id');
            $table->index('service_slug'); // slug au lieu de service_id
            $table->index('demande_par');
            $table->index('valide_par');
            $table->index('code_examen');
            $table->index(['type_origine','statut']);

            // Clés étrangères
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('demande_par')->references('id')->on('personnels')->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('valide_par')->references('id')->on('personnels')->cascadeOnUpdate()->nullOnDelete();

            // Si tu as "factures", décommente :
            $table->foreign('facture_id')->references('id')->on('factures')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examens');
    }
};
