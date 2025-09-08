<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pediatries', function (Blueprint $t) {
            $t->uuid('id')->primary();

            $t->uuid('patient_id');
            $t->uuid('visite_id')->nullable();
            $t->unsignedBigInteger('soignant_id'); // user connecté

            // Données de l’acte pédiatrique
            $t->dateTime('date_acte')->nullable();
            $t->string('motif', 190)->nullable();
            $t->string('diagnostic', 190)->nullable();

            // Constantes vitales & mesures
            $t->decimal('poids', 6, 2)->nullable();      // kg
            $t->decimal('taille', 6, 2)->nullable();     // cm
            $t->decimal('temperature', 4, 1)->nullable();// °C
            $t->decimal('perimetre_cranien', 5, 2)->nullable(); // cm
            $t->unsignedInteger('saturation')->nullable();      // %
            $t->unsignedInteger('frequence_cardiaque')->nullable();   // bpm
            $t->unsignedInteger('frequence_respiratoire')->nullable();// rpm

            // Textes libres
            $t->text('examen_clinique')->nullable();
            $t->text('traitements')->nullable();  // JSON ou texte libre
            $t->text('observation')->nullable();

            $t->enum('statut', ['en_cours','clos','annule'])->default('en_cours');

            $t->timestamps();
            $t->softDeletes();

            // FKs (adapte si besoin)
            $t->foreign('patient_id')->references('id')->on('patients');
            $t->foreign('visite_id')->references('id')->on('visites');
            $t->foreign('soignant_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pediatries');
    }
};
