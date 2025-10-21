<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('declarations_naissance', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Mère = patients.id (UUID)
            $table->foreignUuid('mere_id')->constrained('patients')->cascadeOnDelete();

            // service via slug
            $table->string('service_slug')->nullable()->index();
            $table->foreign('service_slug')
                ->references('slug')->on('services')
                ->cascadeOnUpdate()->nullOnDelete();

            // lien accouchement (si table existe)
            //$table->foreignUuid('accouchement_id')->nullable()->constrained('accouchements')->nullOnDelete();

            // traçabilité
            $table->string('created_via')->nullable(); // service | med | admin
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // données saisies manuellement
            $table->string('bebe_nom')->nullable();
            $table->string('bebe_prenom')->nullable();
            $table->string('pere_nom')->nullable();
            $table->string('pere_prenom')->nullable();

            // données de naissance
            $table->dateTime('date_heure_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('sexe', 1)->nullable(); // M/F/I
            $table->decimal('poids_kg', 5, 2)->nullable();
            $table->decimal('taille_cm', 5, 2)->nullable();
            $table->unsignedTinyInteger('apgar_1')->nullable();
            $table->unsignedTinyInteger('apgar_5')->nullable();

            // état civil & docs
            $table->string('numero_acte')->nullable()->unique();
            $table->string('officier_etat_civil')->nullable();
            $table->json('documents_json')->nullable();

            // facturation
            $table->decimal('prix', 10, 2)->nullable();
            $table->string('devise', 10)->default('XAF');
            $table->foreignUuid('facture_id')->nullable()->constrained('factures')->nullOnDelete();

            // workflow
            $table->string('statut')->default('brouillon'); // brouillon | valide | transmis
            $table->dateTime('date_transmission')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['mere_id','statut']);
            $table->index('date_heure_naissance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declarations_naissance');
    }
};
