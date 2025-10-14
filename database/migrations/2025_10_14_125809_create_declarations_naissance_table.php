<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('declarations_naissance', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mere_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('pere_id')->nullable()->constrained('patients')->nullOnDelete();

            $table->string('service_slug')->nullable()->index();
            $table->foreignUuid('accouchement_id')->nullable()->constrained('accouchements')->nullOnDelete();

            // traçabilité
            $table->string('created_via')->nullable(); // service | med | admin
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // données de naissance
            $table->dateTime('date_heure_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('sexe', 1)->nullable(); // M/F/I
            $table->decimal('poids_kg', 5, 2)->nullable();
            $table->decimal('taille_cm', 5, 2)->nullable();
            $table->unsignedTinyInteger('apgar_1')->nullable();
            $table->unsignedTinyInteger('apgar_5')->nullable();

            // état-civil
            $table->string('numero_acte')->nullable()->unique();
            $table->string('officier_etat_civil')->nullable();
            $table->json('documents_json')->nullable();

            // workflow
            $table->string('statut')->default('brouillon'); // brouillon | valide | transmis
            $table->dateTime('date_transmission')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['patient_id','statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declarations_naissance');
    }
};
