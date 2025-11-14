<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reglements', function (Blueprint $table) {
            if (! Schema::hasColumn('reglements', 'service_id')) {
                $table->foreignId('service_id')
                      ->nullable()
                      ->constrained('services')
                      ->nullOnDelete()
                      ->after('workstation');

                $table->index(['service_id', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('reglements', function (Blueprint $table) {
            if (Schema::hasColumn('reglements', 'service_id')) {
                // supprime l'index si présent
                $table->dropIndex(['service_id', 'created_at']); // ignore si absent
                // supprime la contrainte + colonne
                $table->dropConstrainedForeignId('service_id');
            }
        });
    }
};

