<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pansements', function (Blueprint $table) {
            // PK UUID (string 36)
            $table->char('id', 36)->primary();

            // FK vers patients/visites (UUID char(36))
            $table->char('patient_id', 36);
            $table->char('visite_id', 36)->nullable();

            // Soignant (users.id = BIGINT unsigned)
            $table->unsignedBigInteger('soignant_id')->nullable();

            // Données métier
            $table->timestamp('date_soin')->nullable();
            $table->string('type', 100);
            $table->text('observation')->nullable();
            $table->string('etat_plaque', 150)->nullable();
            $table->text('produits_utilises')->nullable();
            $table->enum('status', ['planifie','en_cours','clos','annule'])->default('en_cours');

            $table->timestamps();
            $table->softDeletes();

            // Index utiles
            $table->index('patient_id');
            $table->index('visite_id');
            $table->index('soignant_id');
            $table->index('date_soin');

            // Contraintes
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('visite_id')->references('id')->on('visites')->nullOnDelete();
            $table->foreign('soignant_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pansements');
    }
};
