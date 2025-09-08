<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('smis', function (Blueprint $t) {
            $t->uuid('id')->primary();

            $t->uuid('patient_id');
            $t->uuid('visite_id')->nullable();
            $t->unsignedBigInteger('soignant_id');

            $t->dateTime('date_acte')->nullable();

            // Données cliniques (fr)
            $t->string('motif', 190)->nullable();
            $t->string('diagnostic', 190)->nullable();
            $t->text('examen_clinique')->nullable();
            $t->text('traitements')->nullable();
            $t->text('observation')->nullable();

            // Signes vitaux (optionnels)
            $t->string('tension_arterielle', 20)->nullable(); // ex: "110/70"
            $t->decimal('temperature', 4, 1)->nullable();
            $t->unsignedSmallInteger('frequence_cardiaque')->nullable();
            $t->unsignedSmallInteger('frequence_respiratoire')->nullable();

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
        Schema::dropIfExists('smis');
    }
};
