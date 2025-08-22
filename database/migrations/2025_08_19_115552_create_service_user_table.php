<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('service_user')) {
            Schema::create('service_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('service_id')->constrained()->cascadeOnDelete();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->unique(['user_id', 'service_id']);
                $table->index(['user_id', 'is_primary']);
            });
        } else {
            // La table existe déjà : on s’assure que les colonnes / index y sont
            Schema::table('service_user', function (Blueprint $table) {
                if (!Schema::hasColumn('service_user', 'is_primary')) {
                    $table->boolean('is_primary')->default(false)->after('service_id');
                }
                // Ajoute les index/contraintes si absents (protégé par try/catch)
                try { $table->unique(['user_id','service_id']); } catch (\Throwable $e) {}
                try { $table->index(['user_id','is_primary']); } catch (\Throwable $e) {}
            });
        }
    }

    public function down(): void
    {
        // Si tu veux la garder en rollback soft, commente la ligne suivante
        Schema::dropIfExists('service_user');
    }
};
