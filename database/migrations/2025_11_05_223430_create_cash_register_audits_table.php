<?php

// database/migrations/2025_11_05_000002_create_cash_register_audits_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cash_register_audits', function (Blueprint $table) {
            $table->id();
            $table->enum('event', ['SESSION_OPENED','PAYMENT_CREATED','SESSION_CLOSED']);
            $table->foreignId('session_id')->constrained('cash_register_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedBigInteger('facture_id')->nullable();
            $table->unsignedBigInteger('reglement_id')->nullable();

            $table->string('workstation', 100)->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['event', 'created_at']);
            $table->index(['user_id']);
            $table->index(['workstation']);
            $table->index(['facture_id']);
            $table->index(['reglement_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('cash_register_audits');
    }
};

