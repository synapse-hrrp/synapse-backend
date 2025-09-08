<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kinesitherapies', function (Blueprint $t) {
            $t->uuid('id')->primary();

            $t->uuid('patient_id');
            $t->uuid('visite_id')->nullable();
            $t->unsignedBigInteger('soignant_id');

            $t->dateTime('date_acte')->nullable();

            // Données kiné (fr)
            $t->string('motif', 190)->nullable();
            $t->string('diagnostic', 190)->nullable();
            $t->text('evaluation')->nullable();          // bilan initial
            $t->text('objectifs')->nullable();           // objectifs de prise en charge
            $t->text('techniques')->nullable();          // techniques utilisées (massage, mobilisation…)
            $t->string('zone_traitee', 190)->nullable(); // épaule droite, lombaires, etc.
            $t->unsignedTinyInteger('intensite_douleur')->nullable(); // 0–10
            $t->unsignedTinyInteger('echelle_borg')->nullable();      // 0–10 effort perçu
            $t->unsignedSmallInteger('nombre_seances')->nullable();   // programme prévu
            $t->unsignedSmallInteger('duree_minutes')->nullable();    // durée de la séance
            $t->text('resultats')->nullable();           // progression constatée
            $t->text('conseils')->nullable();            // à domicile

            $t->enum('statut', ['planifie','en_cours','termine','annule'])->default('planifie');

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
        Schema::dropIfExists('kinesitherapies');
    }
};
