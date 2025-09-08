<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('arus', function (Blueprint $t) {
            $t->uuid('id')->primary();

            $t->uuid('patient_id');
            $t->uuid('visite_id')->nullable();
            $t->unsignedBigInteger('soignant_id');

            $t->dateTime('date_acte')->nullable();

            // Données urgences (fr)
            $t->string('motif', 190)->nullable();
            $t->enum('triage_niveau', ['1','2','3','4','5'])->nullable(); // 1 = critique … 5 = non urgent
            $t->string('tension_arterielle', 20)->nullable();    // "120/80"
            $t->decimal('temperature', 4, 1)->nullable();
            $t->unsignedSmallInteger('frequence_cardiaque')->nullable();
            $t->unsignedSmallInteger('frequence_respiratoire')->nullable();
            $t->unsignedTinyInteger('saturation')->nullable();   // %
            $t->unsignedTinyInteger('douleur_echelle')->nullable(); // 0–10
            $t->unsignedTinyInteger('glasgow')->nullable();      // 3–15

            $t->text('examens_complementaires')->nullable();     // radio, labo urgents…
            $t->text('traitements')->nullable();
            $t->text('observation')->nullable();

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
        Schema::dropIfExists('arus');
    }
};
