<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('maternites','service_id')) {
            Schema::table('maternites', function (Blueprint $table) {
                $table->unsignedBigInteger('service_id')->nullable()->after('visite_id');
                $table->foreign('service_id', 'fk_maternites_service_id')
                      ->references('id')->on('services')
                      ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('maternites','service_id')) {
            Schema::table('maternites', function (Blueprint $table) {
                try { $table->dropForeign('fk_maternites_service_id'); } catch (\Throwable $e) {}
                $table->dropColumn('service_id');
            });
        }
    }
};
