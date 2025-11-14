<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cash_register_audits', function (Blueprint $table) {
            try { $table->index('created_at', 'cash_audit_created_at_idx'); } catch (\Throwable $e) {}
            try { $table->index('event', 'cash_audit_event_idx'); } catch (\Throwable $e) {}
            try { $table->index('session_id', 'cash_audit_session_idx'); } catch (\Throwable $e) {}
            try { $table->index('user_id', 'cash_audit_user_idx'); } catch (\Throwable $e) {}
        });
    }

    public function down(): void
    {
        Schema::table('cash_register_audits', function (Blueprint $table) {
            try { $table->dropIndex('cash_audit_created_at_idx'); } catch (\Throwable $e) {}
            try { $table->dropIndex('cash_audit_event_idx'); } catch (\Throwable $e) {}
            try { $table->dropIndex('cash_audit_session_idx'); } catch (\Throwable $e) {}
            try { $table->dropIndex('cash_audit_user_idx'); } catch (\Throwable $e) {}
        });
    }
};
