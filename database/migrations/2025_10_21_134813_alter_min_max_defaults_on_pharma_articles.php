<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pharma_articles', function (Blueprint $table) {
            $table->integer('min_stock')->default(10)->change();
            $table->integer('max_stock')->default(100)->change();
        });

        // Backfill : remplace les 0 actuels par les nouveaux défauts
        DB::table('pharma_articles')
            ->where('min_stock', 0)
            ->update(['min_stock' => 10]);

        DB::table('pharma_articles')
            ->where('max_stock', 0)
            ->update(['max_stock' => 100]);
    }

    public function down(): void
    {
        Schema::table('pharma_articles', function (Blueprint $table) {
            $table->integer('min_stock')->default(0)->change();
            $table->integer('max_stock')->default(0)->change();
        });
    }
};
