<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('billets_sortie', function (Blueprint $table) {
            // 1) Corriger patient_id => UUID + FK vers patients.id (UUID)
            //    (si la colonne existe déjà en BIGINT, nécessite doctrine/dbal pour ->change())
            if (Schema::hasColumn('billets_sortie', 'patient_id')) {
                // Supprimer l’ancienne contrainte si elle existe (nom classique)
                try { $table->dropForeign('billets_sortie_patient_id_foreign'); } catch (\Throwable $e) {}

                // passer la colonne en uuid (char(36))
                $table->uuid('patient_id')->change();
            } else {
                $table->uuid('patient_id');
            }
            $table->foreign('patient_id')
                ->references('id')->on('patients')
                ->cascadeOnDelete();

            // 2) S’assurer que service_slug pointe bien sur services.slug (string)
            if (!Schema::hasColumn('billets_sortie', 'service_slug')) {
                $table->string('service_slug')->nullable()->index();
            }
            try { $table->dropForeign('billets_sortie_service_slug_foreign'); } catch (\Throwable $e) {}
            $table->foreign('service_slug')
                ->references('slug')->on('services')
                ->cascadeOnUpdate()->nullOnDelete();

            // 3) Colonnes de facturation si absentes
            if (!Schema::hasColumn('billets_sortie', 'prix')) {
                $table->decimal('prix', 10, 2)->nullable()->after('date_sortie_effective');
            }
            if (!Schema::hasColumn('billets_sortie', 'devise')) {
                $table->string('devise', 10)->default('XAF')->after('prix');
            }
            if (!Schema::hasColumn('billets_sortie', 'facture_id')) {
                $table->uuid('facture_id')->nullable()->after('devise');
                $table->foreign('facture_id')
                    ->references('id')->on('factures')
                    ->nullOnDelete();
            }

            // 4) FK signataire (personnels.id en UUID) — au cas où
            if (Schema::hasColumn('billets_sortie', 'signature_par')) {
                try { $table->dropForeign('billets_sortie_signature_par_foreign'); } catch (\Throwable $e) {}
                // si besoin de forcer le type
                // $table->uuid('signature_par')->nullable()->change();
                $table->foreign('signature_par')
                    ->references('id')->on('personnels')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('billets_sortie', function (Blueprint $table) {
            // on enlève la FK facture uniquement (on évite de redescendre le type colonne)
            try { $table->dropForeign('billets_sortie_facture_id_foreign'); } catch (\Throwable $e) {}
            if (Schema::hasColumn('billets_sortie', 'facture_id')) {
                $table->dropColumn('facture_id');
            }

            if (Schema::hasColumn('billets_sortie', 'prix')) {
                $table->dropColumn('prix');
            }
            if (Schema::hasColumn('billets_sortie', 'devise')) {
                $table->dropColumn('devise');
            }
        });
    }
};
