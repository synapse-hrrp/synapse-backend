<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $t) {
            if (!Schema::hasColumn('services','config')) {
                $t->json('config')->nullable()->after('is_active');
            }
            // Supprime ce qui n'est pas essentiel (si existant)
            foreach ([
                'code','webhook_url','webhook_method','webhook_token',
                'webhook_secret','webhook_event','webhook_enabled',
            ] as $col) {
                if (Schema::hasColumn('services', $col)) $t->dropColumn($col);
            }
        });
    }
    public function down(): void
    {
        Schema::table('services', function (Blueprint $t) {
            $t->dropColumn('config');
            // (optionnel) remettre les colonnes supprimées si tu veux gérer le rollback
        });
    }
};
