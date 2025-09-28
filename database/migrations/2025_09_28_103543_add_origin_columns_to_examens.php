<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('examens', function (Blueprint $table) {
            $table->string('created_via', 20)->default('labo')->after('reference_demande'); // 'labo' | 'service'
            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('created_via');
            $table->index(['service_slug','created_via']);
        });
    }
    public function down(): void {
        Schema::table('examens', function (Blueprint $table) {
            $table->dropIndex(['service_slug','created_via']);
            $table->dropColumn(['created_via','created_by_user_id']);
        });
    }
};
