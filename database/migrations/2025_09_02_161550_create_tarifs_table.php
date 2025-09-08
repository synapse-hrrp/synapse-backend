<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // --- Table tarifs ---
        Schema::create('tarifs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();   // ex: CONSULTATION
            $table->string('libelle', 150);         // ex: Consultation
            $table->decimal('montant', 14, 2);      // ex: 5000.00
            $table->string('devise', 8)->default('XAF');
            $table->boolean('is_active')->default(true);

            // Lier le tarif à un service (services.id = BIGINT)
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->foreign('service_id')
                  ->references('id')->on('services')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            $table->timestamps();

            // filtre rapide tarifs actifs par service
            $table->index(['is_active','service_id']);
        });

        // --- Ajouts minimalistes sur visites ---
        if (Schema::hasTable('visites')) {
            Schema::table('visites', function (Blueprint $table) {
                if (!Schema::hasColumn('visites','tarif_id'))      $table->uuid('tarif_id')->nullable()->after('statut');
                if (!Schema::hasColumn('visites','montant_prevu')) $table->decimal('montant_prevu', 14, 2)->nullable()->after('tarif_id');
                if (!Schema::hasColumn('visites','montant_du'))    $table->decimal('montant_du', 14, 2)->nullable()->after('montant_prevu');
                if (!Schema::hasColumn('visites','devise'))        $table->string('devise', 8)->nullable()->after('montant_du');

                $table->index('tarif_id');
            });

            // FK visites.tarif_id -> tarifs.id
            Schema::table('visites', function (Blueprint $table) {
                try {
                    $table->foreign('tarif_id')
                          ->references('id')->on('tarifs')
                          ->nullOnDelete()
                          ->cascadeOnUpdate();
                } catch (\Throwable $e) {
                    // ignore si déjà créée
                }
            });
        }
    }

    public function down(): void {
        if (Schema::hasTable('visites')) {
            Schema::table('visites', function (Blueprint $table) {
                try { $table->dropForeign(['tarif_id']); } catch (\Throwable $e) {}
            });
            Schema::table('visites', function (Blueprint $table) {
                foreach (['tarif_id','montant_prevu','montant_du','devise'] as $c) {
                    if (Schema::hasColumn('visites', $c)) $table->dropColumn($c);
                }
            });
        }

        Schema::dropIfExists('tarifs');
    }
};
