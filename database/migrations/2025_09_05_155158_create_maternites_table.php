<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('maternites', function (Blueprint $t) {
            $t->uuid('id')->primary();

            $t->uuid('patient_id');
            $t->uuid('visite_id')->nullable();
            $t->unsignedBigInteger('soignant_id');

            $t->dateTime('date_acte')->nullable();

            // Données obstétricales / cliniques
            $t->string('motif', 190)->nullable();
            $t->string('diagnostic', 190)->nullable();

            $t->string('terme_grossesse', 50)->nullable();          // ex: "T2", "T3"
            $t->unsignedSmallInteger('age_gestationnel')->nullable(); // en SA (semaines d’aménorrhée)
            $t->boolean('mouvements_foetaux')->nullable();

            // Signes vitaux
            $t->string('tension_arterielle', 20)->nullable(); // "120/80"
            $t->decimal('temperature', 4, 1)->nullable();
            $t->unsignedSmallInteger('frequence_cardiaque')->nullable();
            $t->unsignedSmallInteger('frequence_respiratoire')->nullable();

            // Examen obstétrical
            $t->decimal('hauteur_uterine', 4, 1)->nullable(); // cm
            $t->string('presentation', 50)->nullable();       // céphalique, siège, transverse...
            $t->unsignedSmallInteger('battements_cardiaques_foetaux')->nullable(); // BCF (bpm)
            $t->string('col_uterin', 100)->nullable();        // dilatation, effacement...
            $t->string('pertes', 100)->nullable();            // pertes sanguines/liquides

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
        Schema::dropIfExists('maternites');
    }
};
