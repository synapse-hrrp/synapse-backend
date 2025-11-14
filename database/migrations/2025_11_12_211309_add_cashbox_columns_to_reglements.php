<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reglements', function (Blueprint $table) {
            if (!Schema::hasColumn('reglements', 'cashier_id')) {
                $table->foreignId('cashier_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('reglements', 'cash_session_id')) {
                $table->foreignId('cash_session_id')
                    ->nullable()
                    ->constrained('cash_register_sessions')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('reglements', 'workstation')) {
                $table->string('workstation', 100)->nullable();
            }

            if (!Schema::hasColumn('reglements', 'service_id')) {
                $table->foreignId('service_id')
                    ->nullable()
                    ->constrained('services')
                    ->nullOnDelete();
            }
        });

        // index léger sur workstation (au cas où)
        Schema::table('reglements', function (Blueprint $table) {
            if (Schema::hasColumn('reglements', 'workstation')) {
                try { $table->index('workstation', 'regl_workstation_idx'); } catch (\Throwable $e) {}
            }
        });
    }

    public function down(): void
    {
        Schema::table('reglements', function (Blueprint $table) {
            if (Schema::hasColumn('reglements', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }
            if (Schema::hasColumn('reglements', 'cash_session_id')) {
                $table->dropConstrainedForeignId('cash_session_id');
            }
            if (Schema::hasColumn('reglements', 'cashier_id')) {
                $table->dropConstrainedForeignId('cashier_id');
            }
            if (Schema::hasColumn('reglements', 'workstation')) {
                try { $table->dropIndex('regl_workstation_idx'); } catch (\Throwable $e) {}
                $table->dropColumn('workstation');
            }
        });
    }
};
