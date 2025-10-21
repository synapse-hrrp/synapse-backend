<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('declarations_naissance', function (Blueprint $table) {
            // 1) Corriger mere_id (patients.id en UUID)
            if (Schema::hasColumn('declarations_naissance', 'mere_id')) {
                try { $table->dropForeign('declarations_naissance_mere_id_foreign'); } catch (\Throwable $e) {}
                $table->uuid('mere_id')->change();
            } else {
                $table->uuid('mere_id');
            }
            $table->foreign('mere_id')
                ->references('id')->on('patients')
                ->cascadeOnDelete();

            // 2) service_slug -> services.slug
            if (!Schema::hasColumn('declarations_naissance', 'service_slug')) {
                $table->string('service_slug')->nullable()->index();
            }
            try { $table->dropForeign('declarations_naissance_service_slug_foreign'); } catch (\Throwable $e) {}
            $table->foreign('service_slug')
                ->references('slug')->on('services')
                ->cascadeOnUpdate()->nullOnDelete();

            // 3) Colonnes de facturation si absentes
            if (!Schema::hasColumn('declarations_naissance', 'prix')) {
                $table->decimal('prix', 10, 2)->nullable()->after('date_transmission');
            }
            if (!Schema::hasColumn('declarations_naissance', 'devise')) {
                $table->string('devise', 10)->default('XAF')->after('prix');
            }
            if (!Schema::hasColumn('declarations_naissance', 'facture_id')) {
                $table->uuid('facture_id')->nullable()->after('devise');
                $table->foreign('facture_id')
                    ->references('id')->on('factures')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('declarations_naissance', function (Blueprint $table) {
            try { $table->dropForeign('declarations_naissance_facture_id_foreign'); } catch (\Throwable $e) {}
            if (Schema::hasColumn('declarations_naissance', 'facture_id')) {
                $table->dropColumn('facture_id');
            }
            if (Schema::hasColumn('declarations_naissance', 'prix')) {
                $table->dropColumn('prix');
            }
            if (Schema::hasColumn('declarations_naissance', 'devise')) {
                $table->dropColumn('devise');
            }
        });
    }
};
