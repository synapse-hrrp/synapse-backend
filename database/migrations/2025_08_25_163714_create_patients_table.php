<?php

// database/migrations/2025_08_25_000000_create_patients_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('numero_dossier', 32)->unique(); // généré auto si absent
            $table->string('nom', 100);
            $table->string('prenom', 100);

            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance', 150)->nullable();
            $table->unsignedSmallInteger('age_reporte')->nullable(); // si DOB inconnue
            $table->enum('sexe', ['M','F','X'])->nullable();

            $table->string('nationalite', 80)->nullable();
            $table->string('profession', 120)->nullable();
            $table->text('adresse')->nullable();
            $table->string('quartier', 120)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('statut_matrimonial', 40)->nullable();

            $table->string('proche_nom', 150)->nullable();
            $table->string('proche_tel', 30)->nullable();

            $table->enum('groupe_sanguin', ['A+','A-','B+','B-','AB+','AB-','O+','O-'])->nullable();
            $table->text('allergies')->nullable();

            $table->uuid('assurance_id')->nullable();
            $table->string('numero_assure', 64)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['nom','prenom']);
            $table->index('telephone');
            $table->index('quartier');
        });
    }

    public function down(): void {
        Schema::dropIfExists('patients');
    }
};
