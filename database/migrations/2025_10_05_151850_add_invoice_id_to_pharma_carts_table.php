<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pharma_carts', function (Blueprint $table) {
            $table->uuid('invoice_id')->nullable()->after('status');
            // si tu veux la contrainte et que factures.id est UUID:
            // $table->foreign('invoice_id')->references('id')->on('factures')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pharma_carts', function (Blueprint $table) {
            $table->dropColumn('invoice_id');
        });
    }

};
