<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sanitaires', function (Blueprint $t) {
            $t->uuid('id')->primary();

            // Liens (optionnels) vers patient/visite si l’acte sanitaire concerne un patient précis
            $t->uuid('patient_id')->nullable();
            $t->uuid('visite_id')->nullable();

            // Soignant (user connecté)
            $t->unsignedBigInteger('soignant_id');

            // Datation
            $t->dateTime('date_acte')->nullable();
            $t->dateTime('date_debut')->nullable();
            $t->dateTime('date_fin')->nullable();

            // Typologie d’acte sanitaire
            $t->enum('type_action', [
                'nettoyage','desinfection','sterilisation','collecte_dechets','maintenance_hygiene'
            ])->default('nettoyage');

            // Ciblage de zone
            $t->string('zone', 150)->nullable();       // ex: Bloc opératoire, Salle d’attente
            $t->string('sous_zone', 150)->nullable();  // ex: Lavabo, Table d’examen
            $t->enum('niveau_risque', ['faible','moyen','eleve'])->nullable();

            // Détails opérationnels
            $t->text('produits_utilises')->nullable(); // désinfectants, détergents…
            $t->json('equipe')->nullable();            // liste des intervenants, si utile côté hygiène
            $t->unsignedSmallInteger('duree_minutes')->nullable();
            $t->decimal('cout', 12, 2)->nullable();

            // Suivi
            $t->text('observation')->nullable();
            $t->enum('statut', ['planifie','en_cours','fait','annule'])->default('planifie');

            $t->timestamps();
            $t->softDeletes();

            // FK
            $t->foreign('patient_id')->references('id')->on('patients');
            $t->foreign('visite_id')->references('id')->on('visites');
            $t->foreign('soignant_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanitaires');
    }
};
