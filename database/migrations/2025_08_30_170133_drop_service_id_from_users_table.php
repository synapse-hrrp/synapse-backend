<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('users', 'service_id')) {
            Schema::table('users', function (Blueprint $table) {
                // Si des FK existent déjà, on tente de les dropper proprement
                try { $table->dropForeign(['service_id']); } catch (\Throwable $e) {}
                $table->dropColumn('service_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'service_id')) {
                $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            }
        });
    }
};
