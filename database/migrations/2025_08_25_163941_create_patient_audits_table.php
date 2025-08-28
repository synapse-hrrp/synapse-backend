<?php

// database/migrations/2025_08_25_000100_create_patient_audits_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('patient_audits', function (Blueprint $table) {
            $table->id();
            $table->uuid('patient_id')->index();
            $table->string('action'); // create|update|delete|restore
            $table->json('changes')->nullable(); // diff {champ: {from,to}}
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('patient_audits');
    }
};
