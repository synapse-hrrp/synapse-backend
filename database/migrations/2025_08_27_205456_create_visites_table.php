<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('visites', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // FK patients (UUID)
            $table->foreignUuid('patient_id')
                  ->constrained('patients')
                  ->cascadeOnDelete();

            // FK services (BIGINT UNSIGNED)
            $table->foreignId('service_id')
                  ->constrained('services')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            // FK users (BIGINT UNSIGNED)
            $table->foreignId('medecin_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->foreignId('agent_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Infos redondantes (snapshots)
            $table->string('medecin_nom', 150)->nullable();
            $table->string('agent_nom', 150);

            $table->timestamp('heure_arrivee')->useCurrent();

            $table->text('plaintes_motif')->nullable();
            $table->text('hypothese_diagnostic')->nullable();

            // Optionnel: pas de contrainte si l’entité "affectation" n’existe pas encore
            $table->uuid('affectation_id')->nullable();

            $table->enum('statut', ['ouvert','clos'])->default('ouvert');
            $table->timestamp('clos_at')->nullable();

            $table->timestamps();

            // Index
            $table->index(['patient_id','created_at']);
            $table->index(['service_id','statut']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('visites');
    }
};
