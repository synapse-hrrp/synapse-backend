<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billets_sortie', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // patient (UUID)
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();

            // service via slug
            $table->string('service_slug')->nullable()->index();
            $table->foreign('service_slug')
                ->references('slug')->on('services')
                ->cascadeOnUpdate()->nullOnDelete();

            // reference admission (si module admissions UUID)
            //$table->foreignUuid('admission_id')->nullable()->constrained('admissions')->nullOnDelete();

            // traçabilité
            $table->string('created_via')->nullable(); // service | med | admin
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // contenu clinique
            $table->string('motif_sortie')->nullable();
            $table->text('diagnostic_sortie')->nullable();
            $table->longText('resume_clinique')->nullable();
            $table->longText('consignes')->nullable();
            $table->json('traitement_sortie_json')->nullable();
            $table->dateTime('rdv_controle_at')->nullable();
            $table->string('destination')->nullable();

            // facturation
            $table->decimal('prix', 10, 2)->nullable();
            $table->string('devise', 10)->default('XAF');
            $table->foreignUuid('facture_id')->nullable()->constrained('factures')->nullOnDelete();

            // workflow
            $table->string('statut')->default('brouillon'); // brouillon | valide | remis
            $table->string('remis_a')->nullable();
            //$table->foreignUuid('signature_par')->nullable()->constrained('personnels')->nullOnDelete();
            $table->dateTime('date_signature')->nullable();
            $table->dateTime('date_sortie_effective')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['patient_id','statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billets_sortie');
    }
};
