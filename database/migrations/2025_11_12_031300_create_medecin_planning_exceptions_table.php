<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('medecin_planning_exceptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('medecin_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->boolean('is_working')->default(false);
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->unsignedSmallInteger('slot_duration')->nullable();
            $t->unsignedTinyInteger('capacity_per_slot')->nullable();
            $t->text('reason')->nullable();
            $t->timestamps();

            $t->unique(['medecin_id','date']);
        });
    }
    public function down(): void { Schema::dropIfExists('medecin_planning_exceptions'); }
};
