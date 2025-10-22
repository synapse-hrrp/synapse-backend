<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharma_carts', function (Blueprint $table) {
            if (!Schema::hasColumn('pharma_carts', 'invoice_id')) {
                $table->uuid('invoice_id')->nullable()->after('currency');

                // Si ta table des factures s'appelle `factures`
                $table->foreign('invoice_id')
                      ->references('id')
                      ->on('factures')
                      ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pharma_carts', function (Blueprint $table) {
            if (Schema::hasColumn('pharma_carts', 'invoice_id')) {
                $table->dropForeign(['invoice_id']);
                $table->dropColumn('invoice_id');
            }
        });
    }
};
