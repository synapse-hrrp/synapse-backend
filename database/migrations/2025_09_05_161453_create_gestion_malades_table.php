<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gestion_malades', function (Blueprint $t) {
            $t->uuid('id')->primary();

            $t->uuid('patient_id');
            $t->uuid('visite_id')->nullable();
            $t->unsignedBigInteger('soignant_id');

            $t->dateTime('date_acte')->nullable();

            // Domaine "gestion malade" (admissions / transferts / sorties…)
            $t->enum('type_action', ['admission','transfert','hospitalisation','sortie'])->default('admission');

            $t->string('service_source', 120)->nullable();
            $t->string('service_destination', 120)->nullable();

            // Hébergement
            $t->string('pavillon', 120)->nullable();
            $t->string('chambre', 60)->nullable();
            $t->string('lit', 60)->nullable();

            // Dates utiles
            $t->dateTime('date_entree')->nullable();
            $t->dateTime('date_sortie_prevue')->nullable();
            $t->dateTime('date_sortie_effective')->nullable();

            // Clinique
            $t->string('motif', 190)->nullable();
            $t->string('diagnostic', 190)->nullable();
            $t->text('examen_clinique')->nullable();
            $t->text('traitements')->nullable();
            $t->text('observation')->nullable();

            $t->enum('statut', ['en_cours','clos','annule'])->default('en_cours');

            $t->timestamps();
            $t->softDeletes();

            $t->foreign('patient_id')->references('id')->on('patients');
            $t->foreign('visite_id')->references('id')->on('visites');
            $t->foreign('soignant_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestion_malades');
    }
};
