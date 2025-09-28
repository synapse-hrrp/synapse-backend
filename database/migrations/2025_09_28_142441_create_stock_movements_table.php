<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reagent_id')->constrained()->cascadeOnDelete();
            $t->foreignId('reagent_lot_id')->nullable()->constrained('reagent_lots')->nullOnDelete();
            $t->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            $t->enum('type', ['OPENING','IN','OUT','ADJUST','TRANSFER','DISPOSAL','RETURN']);
            $t->decimal('quantity', 20,6);               // > 0
            $t->timestamp('moved_at')->useCurrent();
            $t->string('reference')->nullable();         // N° analyse, BR, etc.
            $t->decimal('unit_cost', 20,6)->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->text('notes')->nullable();

            $t->timestamps();
            $t->index(['reagent_id','moved_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('stock_movements'); }
};
