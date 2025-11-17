<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reglements', function (Blueprint $table) {
            // caissier (Personnel)
            if (!Schema::hasColumn('reglements', 'cashier_id')) {
                $table->uuid('cashier_id')->nullable()->after('facture_id');
                $table->foreign('cashier_id')
                      ->references('id')
                      ->on('personnels');
            }

            // session de caisse
            if (!Schema::hasColumn('reglements', 'cash_session_id')) {
                $table->uuid('cash_session_id')->nullable()->after('cashier_id');
                $table->foreign('cash_session_id')
                      ->references('id')
                      ->on('cash_register_sessions'); // adapte le nom si différent
            }

            // poste de travail
            if (!Schema::hasColumn('reglements', 'workstation')) {
                $table->string('workstation')->nullable()->after('cash_session_id');
            }

            // service (optionnel mais recommandé)
            if (!Schema::hasColumn('reglements', 'service_id')) {
                $table->uuid('service_id')->nullable()->after('workstation');
                $table->foreign('service_id')
                      ->references('id')
                      ->on('services');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reglements', function (Blueprint $table) {
            if (Schema::hasColumn('reglements', 'cashier_id')) {
                $table->dropForeign(['cashier_id']);
                $table->dropColumn('cashier_id');
            }

            if (Schema::hasColumn('reglements', 'cash_session_id')) {
                $table->dropForeign(['cash_session_id']);
                $table->dropColumn('cash_session_id');
            }

            if (Schema::hasColumn('reglements', 'service_id')) {
                $table->dropForeign(['service_id']);
                $table->dropColumn('service_id');
            }

            if (Schema::hasColumn('reglements', 'workstation')) {
                $table->dropColumn('workstation');
            }
        });
    }
};
