<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hospitalisations', function (Blueprint $table) {
            if (!Schema::hasColumn('hospitalisations', 'prix')) {
                $table->decimal('prix', 10, 2)->nullable()->after('date_sortie_reelle');
            }
            if (!Schema::hasColumn('hospitalisations', 'devise')) {
                $table->string('devise', 10)->default('XAF')->after('prix');
            }
            if (!Schema::hasColumn('hospitalisations', 'facture_id')) {
                $table->uuid('facture_id')->nullable()->after('devise');
                $table->foreign('facture_id')
                    ->references('id')->on('factures')
                    ->nullOnDelete();
            }

            // (rappel FK medecin_traitant_id -> personnels.id UUID)
            if (Schema::hasColumn('hospitalisations', 'medecin_traitant_id')) {
                try { $table->dropForeign('hospitalisations_medecin_traitant_id_foreign'); } catch (\Throwable $e) {}
                // $table->uuid('medecin_traitant_id')->nullable()->change(); // si pas déjà uuid
                $table->foreign('medecin_traitant_id')
                    ->references('id')->on('personnels')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('hospitalisations', function (Blueprint $table) {
            try { $table->dropForeign('hospitalisations_facture_id_foreign'); } catch (\Throwable $e) {}
            if (Schema::hasColumn('hospitalisations', 'facture_id')) {
                $table->dropColumn('facture_id');
            }
            if (Schema::hasColumn('hospitalisations', 'prix')) {
                $table->dropColumn('prix');
            }
            if (Schema::hasColumn('hospitalisations', 'devise')) {
                $table->dropColumn('devise');
            }
        });
    }
};
