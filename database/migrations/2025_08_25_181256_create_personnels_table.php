<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('personnels', function (Blueprint $table) {
            $table->id();

            // 1–à–1 avec users
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete()
                  ->unique();

            // pro/perso
            $table->string('matricule')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->enum('sex', ['M','F'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('cin')->nullable()->unique();
            $table->string('phone_alt')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('job_title')->nullable();
            $table->date('hired_at')->nullable();

            // rattachement à un service EXISTANT
            $table->foreignId('service_id')
                  ->nullable()
                  ->constrained('services')
                  ->nullOnDelete();

            $table->string('avatar_path')->nullable();
            $table->json('extra')->nullable();

            $table->timestamps();

            $table->index(['last_name','first_name']);
            $table->index('service_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('personnels');
    }
};
