<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('reagents', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('sku')->unique();
            $t->string('uom')->default('unit');          // mL, g, pcs...
            // Infos labo
            $t->string('cas_number')->nullable();
            $t->string('hazard_class')->nullable();      // GHS/CLP
            $t->decimal('storage_temp_min', 5,2)->nullable();
            $t->decimal('storage_temp_max', 5,2)->nullable();
            $t->string('storage_conditions')->nullable();// "à l'abri de la lumière"
            $t->string('concentration')->nullable();     // "0.5M", "10X"
            $t->decimal('container_size', 12,3)->nullable();
            $t->string('location_default')->nullable();
            $t->decimal('min_stock', 20,6)->default(0);
            $t->decimal('reorder_point', 20,6)->default(0);

            // Caches
            $t->decimal('current_stock', 20,6)->default(0);

            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('reagents'); }
};
