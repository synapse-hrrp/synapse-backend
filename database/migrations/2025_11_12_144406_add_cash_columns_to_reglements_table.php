<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reglements', function (Blueprint $table) {
            // ── Colonnes (créées seulement si absentes) ───────────────────────
            if (!Schema::hasColumn('reglements', 'cashier_id')) {
                $table->foreignId('cashier_id')
                    ->nullable()
                    ->after('devise')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('reglements', 'cash_session_id')) {
                $table->foreignId('cash_session_id')
                    ->nullable()
                    ->after('cashier_id')
                    ->constrained('cash_register_sessions')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('reglements', 'workstation')) {
                $table->string('workstation', 100)
                    ->nullable()
                    ->after('cash_session_id');
            }

            if (!Schema::hasColumn('reglements', 'service_id')) {
                $table->foreignId('service_id')
                    ->nullable()
                    ->after('workstation')
                    ->constrained('services')
                    ->nullOnDelete();
            }

            // ⚠️ NE PAS créer d’index ici sur created_at, service_id, etc.
            // - created_at est déjà indexé ailleurs (migration 2025_11_11_140318_...).
            // - les foreignId créent déjà un index pour leurs colonnes.
        });
    }

    public function down(): void
    {
        Schema::table('reglements', function (Blueprint $table) {
            // ── Drop FKs (silencieux si déjà absentes) ────────────────────────
            try { $table->dropForeign(['cashier_id']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['cash_session_id']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['service_id']); } catch (\Throwable $e) {}

            // ── Drop colonnes ────────────────────────────────────────────────
            try { $table->dropColumn('cashier_id'); } catch (\Throwable $e) {}
            try { $table->dropColumn('cash_session_id'); } catch (\Throwable $e) {}
            try { $table->dropColumn('workstation'); } catch (\Throwable $e) {}
            try { $table->dropColumn('service_id'); } catch (\Throwable $e) {}
        });
    }
};
