<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medecines', function (Blueprint $table) {
            // PK en UUID comme dans ton modèle
            $table->uuid('id')->primary();

            // Adapter ces deux lignes SI patients.id / visites.id ne sont pas en uuid
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignUuid('visite_id')->nullable()->constrained('visites')->nullOnDelete();

            // ICI la correction: users.id est généralement un BIGINT UNSIGNED
            $table->foreignId('soignant_id')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('date_acte')->nullable();

            $table->string('motif')->nullable();
            $table->text('diagnostic')->nullable();
            $table->text('examen_clinique')->nullable();
            $table->text('traitements')->nullable();
            $table->text('observation')->nullable();

            $table->string('tension_arterielle')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->unsignedSmallInteger('frequence_cardiaque')->nullable();
            $table->unsignedSmallInteger('frequence_respiratoire')->nullable();

            $table->string('statut')->default('en_cours');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medecines');
    }
};
