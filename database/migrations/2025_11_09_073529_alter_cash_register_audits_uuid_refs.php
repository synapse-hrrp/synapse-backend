<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Si tu n'as pas doctrine/dbal, on passe par du SQL brut (safe ici)
        // Passe facture_id & reglement_id en CHAR(36) NULL + index.
        DB::statement("ALTER TABLE cash_register_audits MODIFY facture_id CHAR(36) NULL");
        DB::statement("ALTER TABLE cash_register_audits MODIFY reglement_id CHAR(36) NULL");

        // Index (au cas où ils n’existent pas déjà)
        try { Schema::table('cash_register_audits', function (Blueprint $table) {
            $table->index('facture_id', 'cash_audit_facture_id_idx');
            $table->index('reglement_id', 'cash_audit_reglement_id_idx');
        }); } catch (\Throwable $e) { /* ignore si déjà indexé */ }
    }

    public function down(): void
    {
        // Revenir en INT si jamais (adapter à ton ancien type exact si besoin)
        DB::statement("ALTER TABLE cash_register_audits MODIFY facture_id INT NULL");
        DB::statement("ALTER TABLE cash_register_audits MODIFY reglement_id INT NULL");

        try { Schema::table('cash_register_audits', function (Blueprint $table) {
            $table->dropIndex('cash_audit_facture_id_idx');
            $table->dropIndex('cash_audit_reglement_id_idx');
        }); } catch (\Throwable $e) { /* ignore */ }
    }
};
