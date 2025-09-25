<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kinesitherapies', function (Blueprint $table) {
            if (! Schema::hasColumn('kinesitherapies', 'service_id')) {
                $table->uuid('service_id')->nullable()->after('visite_id');
                $table->foreign('service_id')
                      ->references('id')->on('services')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('kinesitherapies', function (Blueprint $table) {
            if (Schema::hasColumn('kinesitherapies', 'service_id')) {
                $table->dropForeign(['service_id']);
                $table->dropColumn('service_id');
            }
        });
    }
};
