<?php

// database/migrations/2025_11_06_000000_create_user_service_pivot.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_service', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('service_id'); // adapte en UUID si besoin
            $table->timestamps();

            $table->primary(['user_id','service_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::dropIfExists('user_service');
    }
};

