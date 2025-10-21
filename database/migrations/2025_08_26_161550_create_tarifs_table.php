<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tarifs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('libelle', 150);
            $table->decimal('montant', 14, 2);
            $table->string('devise', 8)->default('XAF');
            $table->boolean('is_active')->default(true);

            // Lien avec service
            $table->foreignId('service_id')
                  ->nullable()
                  ->constrained('services')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            $table->timestamps();
            $table->index(['is_active', 'service_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('tarifs');
    }
};
