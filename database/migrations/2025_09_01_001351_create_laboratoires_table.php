<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('laboratoires', function (Blueprint $table) {
            $table->char('id', 36)->primary();

            // UUID vers patients/visites
            $table->char('patient_id', 36);
            $table->char('visite_id', 36)->nullable();

            // Champs labo
            $table->string('test_code', 50);
            $table->string('test_name', 150);
            $table->string('specimen', 100)->nullable();

            $table->enum('status', ['pending','in_progress','validated','canceled'])->default('pending');

            $table->string('result_value', 100)->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('ref_range', 150)->nullable();
            $table->json('result_json')->nullable();

            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->char('invoice_id', 36)->nullable();

            // Vers users.id (BIGINT)
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('requested_at')->nullable();

            $table->unsignedBigInteger('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // FK UUID
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('visite_id')->references('id')->on('visites')->nullOnDelete();

            // FK vers users (BIGINT)
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('validated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratoires');
    }
};
