<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('locations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('path')->nullable();              // "Salle A > Frigo 2 > Bac haut"
            $t->boolean('is_cold_chain')->default(false);
            $t->decimal('temp_range_min', 5,2)->nullable();
            $t->decimal('temp_range_max', 5,2)->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('locations'); }
};
