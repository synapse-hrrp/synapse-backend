<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('medecin_plannings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('medecin_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('weekday'); // 1=lundi ... 7=dimanche
            $t->time('start_time');
            $t->time('end_time');
            $t->unsignedSmallInteger('slot_duration')->default(20);
            $t->unsignedTinyInteger('capacity_per_slot')->default(1);
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->unique(['medecin_id','weekday','start_time','end_time'], 'uniq_planning_segment');
        });
    }
    public function down(): void { Schema::dropIfExists('medecin_plannings'); }
};
