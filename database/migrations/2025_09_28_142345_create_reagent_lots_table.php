<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('reagent_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reagent_id')->constrained()->cascadeOnDelete();
            $t->string('lot_code');
            $t->date('expiry_date')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->decimal('initial_qty', 20,6)->default(0);
            $t->decimal('current_qty', 20,6)->default(0);
            $t->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status')->default('ACTIVE'); // ACTIVE|QUARANTINE|EXPIRED|DISPOSED
            $t->string('coa_url')->nullable();
            $t->string('barcode')->nullable();

            $t->unique(['reagent_id','lot_code']);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('reagent_lots'); }
};
