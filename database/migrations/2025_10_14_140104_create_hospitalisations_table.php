<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hospitalisations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('service_slug')->nullable()->index();

            $table->string('admission_no')->nullable()->unique();
            $table->string('created_via')->nullable(); // service | med | admin
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // logistique
            $table->string('unite')->nullable();
            $table->string('chambre')->nullable();
            $table->string('lit')->nullable();
            $table->unsignedBigInteger('lit_id')->nullable()->index(); // si table "lits" existe
            $table->foreignUuid('medecin_traitant_id')->nullable()->constrained('personnels')->nullOnDelete();

            // clinique
            $table->text('motif_admission')->nullable();
            $table->text('diagnostic_entree')->nullable();
            $table->text('diagnostic_sortie')->nullable();
            $table->longText('notes')->nullable();
            $table->json('prise_en_charge_json')->nullable();

            // workflow & dates
            $table->string('statut')->default('en_cours'); // en_cours | transfere | sorti | annule
            $table->dateTime('date_admission')->nullable();
            $table->dateTime('date_sortie_prevue')->nullable();
            $table->dateTime('date_sortie_reelle')->nullable();

            // facturation
            $table->foreignUuid('facture_id')->nullable()->constrained('factures')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['patient_id','statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitalisations');
    }
};
