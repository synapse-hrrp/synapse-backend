<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('visites', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // 🔗 Patient (UUID)
            $table->foreignUuid('patient_id')
                  ->constrained('patients')
                  ->cascadeOnDelete();

            // 🔗 Service (BIGINT UNSIGNED)
            $table->foreignId('service_id')
                  ->constrained('services')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            // 🔗 Médecin (User)
            $table->foreignId('medecin_id')->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // 🔗 Agent (User)
            $table->foreignId('agent_id')->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Noms instantanés (snapshots)
            $table->string('medecin_nom', 150)->nullable();
            $table->string('agent_nom', 150)->nullable();

            // Horodatage d'arrivée
            $table->timestamp('heure_arrivee')->nullable();

            // Informations médicales
            $table->text('plaintes_motif')->nullable();
            $table->text('hypothese_diagnostic')->nullable();

            // 🔗 Affectation (optionnelle)
            $table->uuid('affectation_id')->nullable();

            // 💰 Tarification
            $table->foreignUuid('tarif_id')
                  ->nullable()
                  ->constrained('tarifs')
                  ->nullOnDelete();

            $table->decimal('montant_prevu', 14, 2)->nullable();
            $table->decimal('montant_du', 14, 2)->nullable();
            $table->string('devise', 10)->nullable();

            // 📊 Statut et clôture
            $table->enum('statut', ['EN_ATTENTE','A_ENCAISSER','PAYEE','CLOTUREE'])
                  ->default('EN_ATTENTE');

            $table->timestamp('clos_at')->nullable();
            $table->timestamps();

            // 🧩 Index
            $table->index(['patient_id', 'created_at']);
            $table->index(['service_id', 'statut']);
            $table->index(['medecin_id']);
            $table->index(['agent_id']);
            $table->index(['tarif_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visites');
    }
};
