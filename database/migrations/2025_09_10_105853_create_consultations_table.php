<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $t) {
            $t->uuid('id')->primary();

            $t->uuid('patient_id');
            $t->uuid('visite_id')->nullable();
            $t->unsignedBigInteger('soignant_id'); // user connecté (créateur)

            $t->dateTime('date_acte')->nullable();

            // Typage flexible
            $t->string('categorie', 50)->nullable();          // externe, gynecologie, pediatrie, urgence, suivi, etc.
            $t->string('type_consultation', 50)->nullable();  // simple, prenatal, postnatal, controle, ...

            // Champs cliniques génériques (fr)
            $t->string('motif', 190)->nullable();
            $t->text('examen_clinique')->nullable();
            $t->string('diagnostic', 190)->nullable();
            $t->text('prescriptions')->nullable();
            $t->text('orientation_service')->nullable();

            // Spécifiques (flex)
            $t->json('donnees_specifiques')->nullable();

            // Statut
            $t->enum('statut', ['en_cours','clos','annule'])->default('en_cours');

            $t->timestamps();
            $t->softDeletes();

            // FK
            $t->foreign('patient_id')->references('id')->on('patients');
            $t->foreign('visite_id')->references('id')->on('visites');
            $t->foreign('soignant_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
