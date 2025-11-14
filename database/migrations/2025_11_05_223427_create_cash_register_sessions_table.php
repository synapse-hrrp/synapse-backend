<?php

// database/migrations/2025_11_05_000001_create_cash_register_sessions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cash_register_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('workstation', 100);
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('currency', 10)->default('XAF');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->string('opening_note', 255)->nullable();
            $table->string('closing_note', 255)->nullable();
            $table->unsignedInteger('payments_count')->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);

            // colonne générée: 1 si session ouverte
            $table->boolean('is_open')->storedAs('CASE WHEN closed_at IS NULL THEN 1 ELSE 0 END');

            $table->timestamps();

            // recherche fréquente
            $table->index(['user_id', 'workstation']);
            $table->index(['service_id']);
            $table->index(['is_open']);

            // 1 seule session ouverte par (user, workstation)
            $table->unique(['user_id', 'workstation', 'is_open'], 'uniq_open_session_per_user_ws');
        });
    }

    public function down(): void {
        Schema::dropIfExists('cash_register_sessions');
    }
};

