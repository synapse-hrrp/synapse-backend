<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Drop l'ancien unique (adapte le nom si différent)
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            try { $table->dropUnique('uniq_open_session_per_user_ws'); } catch (\Throwable $e) {}
        });

        // 2) Drop l’ancienne colonne générée si on l’avait
        try { DB::statement("ALTER TABLE cash_register_sessions DROP COLUMN is_open"); } catch (\Throwable $e) {}

        // 3) Ajouter une colonne générée qui vaut workstation si ouverte, sinon NULL
        DB::statement("
            ALTER TABLE cash_register_sessions
            ADD COLUMN open_key VARCHAR(100)
            AS (CASE WHEN closed_at IS NULL THEN workstation ELSE NULL END)
            STORED
        ");

        // 4) Unicité seulement quand open_key n'est PAS NULL (donc sessions ouvertes)
        DB::statement("
            ALTER TABLE cash_register_sessions
            ADD UNIQUE KEY uniq_open_session_per_user_ws (user_id, open_key)
        ");
    }

    public function down(): void
    {
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            try { $table->dropUnique('uniq_open_session_per_user_ws'); } catch (\Throwable $e) {}
        });
        try { DB::statement("ALTER TABLE cash_register_sessions DROP COLUMN open_key"); } catch (\Throwable $e) {}
    }
};
