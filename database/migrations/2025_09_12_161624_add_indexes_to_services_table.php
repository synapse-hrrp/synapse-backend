<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        if (! $this->hasIndex('services', 'services_is_active_index')) {
            Schema::table('services', function (Blueprint $table) {
                $table->index('is_active');
            });
        }
    }

    public function down(): void {
        if ($this->hasIndex('services', 'services_is_active_index')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropIndex('services_is_active_index');
            });
        }
    }

    // Helpers (MySQL)
    private function hasIndex(string $table, string $index): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $index]
        ) !== null;
    }
};
