<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visite_proxies', function (Blueprint $t) {
            $t->id();

            // Id unique venant du core
            $t->uuid('visite_id')->unique();

            // Identification du service
            $t->string('service_slug', 100)->index();

            // Patient / acteurs
            $t->uuid('patient_id');
            $t->uuid('medecin_id')->nullable();
            $t->string('medecin_nom', 150)->nullable();
            $t->uuid('agent_id')->nullable();
            $t->string('agent_nom', 150)->nullable();

            // Infos médicales
            $t->timestamp('heure_arrivee')->nullable();
            $t->string('plaintes_motif', 255)->nullable();
            $t->string('hypothese', 255)->nullable();
            $t->string('statut', 100)->nullable();

            // Tarifs & prix
            $t->unsignedBigInteger('tarif_id')->nullable();
            $t->decimal('montant_prevu', 12, 2)->default(0);
            $t->decimal('montant_du', 12, 2)->default(0);
            $t->string('devise', 8)->default('XAF');
            $t->boolean('est_soldee')->default(false);

            // Horodatages source
            $t->timestamp('source_created_at')->nullable();
            $t->timestamp('source_updated_at')->nullable();

            // Payload brut (JSON)
            $t->json('raw')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visite_proxies');
    }
};
