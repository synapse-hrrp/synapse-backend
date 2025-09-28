<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_exam_events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('service_slug');           // FK logique vers services.slug
            $table->uuid('examen_id');                // FK vers examens.id
            $table->string('action');                 // created | updated | deleted | etc.
            $table->unsignedBigInteger('actor_user_id')->nullable(); // qui a déclenché l’action

            $table->json('meta')->nullable();         // détails complémentaires
            $table->timestamps();

            // Index
            $table->index('service_slug');
            $table->index('examen_id');

            // Relations logiques (pas de cascade pour ne pas perdre l’historique)
            // Si tu veux une contrainte forte :
            $table->foreign('service_slug')->references('slug')->on('services');
            $table->foreign('examen_id')->references('id')->on('examens');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_exam_events');
    }
};
