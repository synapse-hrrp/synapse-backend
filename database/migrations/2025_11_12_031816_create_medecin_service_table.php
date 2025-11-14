<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('medecin_service', function (Blueprint $t) {
            $t->id();
            $t->foreignId('medecin_id')->constrained()->cascadeOnDelete();
            $t->string('service_slug');
            $t->foreign('service_slug')->references('slug')->on('services')->cascadeOnDelete();

            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('slot_duration')->nullable();
            $t->unsignedTinyInteger('capacity_per_slot')->nullable();
            $t->timestamps();

            $t->unique(['medecin_id','service_slug']);
        });
    }
    public function down(): void { Schema::dropIfExists('medecin_service'); }
};
