<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bloc_operatoires', function (Blueprint $t) {
            $t->uuid('id')->primary();

            $t->uuid('patient_id');
            $t->uuid('visite_id')->nullable();

            // Equipe
            $t->unsignedBigInteger('soignant_id');      // user connecté (créateur / responsable)
            $t->unsignedBigInteger('chirurgien_id')->nullable();
            $t->unsignedBigInteger('anesthesiste_id')->nullable();
            $t->unsignedBigInteger('infirmier_bloc_id')->nullable();

            // Infos opératoires
            $t->dateTime('date_intervention')->nullable();
            $t->string('type_intervention', 190)->nullable();   // appendicectomie, césarienne, etc.
            $t->string('cote', 30)->nullable();                 // gauche/droite/bilatérale
            $t->string('classification_asa', 10)->nullable();   // ASA I..VI
            $t->enum('type_anesthesie', ['generale','rachianesthesie','locale','sedation','autre'])->nullable();

            $t->time('heure_entree_bloc')->nullable();
            $t->time('heure_debut')->nullable();
            $t->time('heure_fin')->nullable();
            $t->time('heure_sortie_bloc')->nullable();
            $t->unsignedSmallInteger('duree_minutes')->nullable();

            // Données cliniques
            $t->text('indication')->nullable();
            $t->text('gestes_realises')->nullable();
            $t->text('compte_rendu')->nullable();
            $t->text('incident_accident')->nullable();
            $t->text('pertes_sanguines')->nullable();     // estimation ml
            $t->text('antibioprophylaxie')->nullable();

            // Suites
            $t->enum('destination_postop', ['sspi','reanimation','service','domicile'])->nullable();
            $t->text('consignes_postop')->nullable();
            $t->enum('statut', ['planifie','en_cours','clos','annule'])->default('planifie');

            $t->timestamps();
            $t->softDeletes();

            // FK
            $t->foreign('patient_id')->references('id')->on('patients');
            $t->foreign('visite_id')->references('id')->on('visites');
            $t->foreign('soignant_id')->references('id')->on('users');
            $t->foreign('chirurgien_id')->references('id')->on('users');
            $t->foreign('anesthesiste_id')->references('id')->on('users');
            $t->foreign('infirmier_bloc_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloc_operatoires');
    }
};
