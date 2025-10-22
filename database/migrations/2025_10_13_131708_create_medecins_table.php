<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medecins', function (Blueprint $table) {
            $table->id();

            // Relation 1–1 avec personnels
            $table->foreignId('personnel_id')
                ->unique()
                ->constrained('personnels')
                ->cascadeOnDelete();

            // Champs métier
            $table->string('numero_ordre')->unique();
            $table->string('specialite');
            $table->string('grade')->nullable();

            // Index utiles
            $table->index(['specialite', 'grade']);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medecins');
    }
};
