<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            // Ajout d'un service_id optionnel relié à services.id
            $table->foreignId('service_id')
                ->nullable()
                ->after('patient_id')
                ->constrained('services')
                ->nullOnDelete();

            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropIndex(['service_id']);
            $table->dropColumn('service_id');
        });
    }
};
