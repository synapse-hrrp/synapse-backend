<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('rendez_vous', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('medecin_id')->constrained()->cascadeOnDelete();
            $t->uuid('patient_id');
            $t->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();

            // Service sera ajouté dans une migration suivante
            $t->date('date');
            $t->time('start_time');
            $t->time('end_time');

            $t->enum('status', ['pending','confirmed','cancelled','noshow','done'])->default('pending');
            $t->string('source')->nullable();
            $t->text('notes')->nullable();
            $t->text('cancel_reason')->nullable();

            $t->timestamps();

            $t->index(['medecin_id','date','start_time']);
            $t->unique(['patient_id','medecin_id','date','start_time'], 'uniq_patient_slot');
        });
    }
    public function down(): void { Schema::dropIfExists('rendez_vous'); }
};
