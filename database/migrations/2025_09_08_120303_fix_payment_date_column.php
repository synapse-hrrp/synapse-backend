<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            if (!Schema::hasColumn('payments', 'date_paiement')) {
                $t->dateTime('date_paiement')->nullable()->after('methode');
            }
        });

        // Copier les anciennes valeurs vers la nouvelle colonne
        if (Schema::hasColumn('payments', 'paye_le')) {
            DB::statement('UPDATE payments SET date_paiement = paye_le WHERE date_paiement IS NULL');
        }

        // Supprimer l’ancienne colonne
        Schema::table('payments', function (Blueprint $t) {
            if (Schema::hasColumn('payments', 'paye_le')) {
                $t->dropColumn('paye_le');
            }
        });
    }

    public function down(): void
    {
        // Recréer paye_le si on rollback (optionnel)
        Schema::table('payments', function (Blueprint $t) {
            if (!Schema::hasColumn('payments', 'paye_le')) {
                $t->timestamp('paye_le')->nullable()->after('methode');
            }
        });

        // Recopier dans l’autre sens
        if (Schema::hasColumn('payments', 'date_paiement')) {
            DB::statement('UPDATE payments SET paye_le = date_paiement WHERE paye_le IS NULL');
        }

        // Puis retirer date_paiement
        Schema::table('payments', function (Blueprint $t) {
            if (Schema::hasColumn('payments', 'date_paiement')) {
                $t->dropColumn('date_paiement');
            }
        });
    }
};
