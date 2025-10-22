<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accouchements', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // mère (patients.id en UUID)
            $table->foreignUuid('mere_id')->constrained('patients')->cascadeOnDelete();

            // service via slug
            $table->string('service_slug')->nullable()->index();
            $table->foreign('service_slug')
                ->references('slug')->on('services')
                ->cascadeOnUpdate()->nullOnDelete();

            // traçabilité
            $table->string('created_via')->nullable(); // service | med | admin
            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // données obstétricales (exemples courants)
            $table->dateTime('date_heure_accouchement')->nullable();
            $table->unsignedSmallInteger('terme_gestationnel_sa')->nullable(); // semaines d’aménorrhée
            $table->string('voie', 20)->nullable();            // voie basse / césarienne
            $table->string('presentation', 30)->nullable();    // céphalique, siège, transverse...
            $table->string('type_cesarienne', 50)->nullable(); // si césarienne
            $table->string('score_apgar_1_5')->nullable();     // ex: "8/10"
            $table->decimal('poids_kg', 5, 2)->nullable();
            $table->decimal('taille_cm', 5, 2)->nullable();
            $table->string('sexe', 1)->nullable();             // M/F/I

            $table->json('complications_json')->nullable();    // hémorragie, réanimation NN, etc.
            $table->longText('notes')->nullable();

            // workflow
            $table->string('statut')->default('brouillon');    // brouillon | valide | clos

            // facturation auto
            $table->decimal('prix', 10, 2)->nullable();
            $table->string('devise', 10)->default('XAF');
            $table->foreignUuid('facture_id')->nullable()
                ->constrained('factures')->nullOnDelete();

            // personnels (UUID)
            //$table->foreignUuid('sage_femme_id')->nullable()
               // ->constrained('personnels')->nullOnDelete();
            //$table->foreignUuid('obstetricien_id')->nullable()
               // ->constrained('personnels')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['mere_id','statut']);
            $table->index('date_heure_accouchement');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accouchements');
    }
};
