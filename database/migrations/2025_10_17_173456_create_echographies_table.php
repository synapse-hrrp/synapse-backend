<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('echographies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // patients.id (UUID)
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();

            // services.slug (string)
            $table->string('service_slug')->nullable()->index();
            $table->foreign('service_slug')
                ->references('slug')->on('services')
                ->cascadeOnUpdate()->nullOnDelete();

            // origine + traçabilité
            $table->string('type_origine')->nullable();            // interne | externe
            $table->string('prescripteur_externe')->nullable();
            $table->string('reference_demande')->nullable();
            $table->string('created_via')->nullable();             // labo | service

            // users.id (BIGINT)
            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // informations échographie
            $table->string('code_echo')->nullable()->index();
            $table->string('nom_echo')->nullable();
            $table->text('indication')->nullable();
            $table->string('statut')->default('en_attente');       // en_attente | en_cours | termine | valide
            $table->longText('compte_rendu')->nullable();
            $table->text('conclusion')->nullable();
            $table->json('mesures_json')->nullable();
            $table->json('images_json')->nullable();

            // facturation
            $table->decimal('prix', 10, 2)->nullable();
            $table->string('devise', 10)->default('XAF');
            $table->foreignUuid('facture_id')->nullable()
                ->constrained('factures')->nullOnDelete();

            // === workflow (personnels.id est BIGINT → foreignId) ===
            $table->foreignId('demande_par')->nullable()
                ->constrained('personnels')->nullOnDelete();

            $table->dateTime('date_demande')->nullable();

            $table->foreignId('realise_par')->nullable()
                ->constrained('personnels')->nullOnDelete();

            $table->dateTime('date_realisation')->nullable();

            $table->foreignId('valide_par')->nullable()
                ->constrained('personnels')->nullOnDelete();

            $table->dateTime('date_validation')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // index utiles
            $table->index(['patient_id','statut']);
            $table->index('date_demande');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('echographies');
    }
};
