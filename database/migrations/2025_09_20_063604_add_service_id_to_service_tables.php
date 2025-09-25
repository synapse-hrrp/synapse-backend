<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        $tables = [
            'laboratoires','pansements','pediatries','smi','consultations',
            'sanitaires','kinesitherapies','gynecologies','maternites',
            'arus','bloc_operatoires','medecines',
        ];
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table,'service_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('service_id')->nullable()->after('patient_id')
                      ->constrained('services')->nullOnDelete();
                    $t->index('service_id');
                });
            }
        }
    }

    public function down(): void {
        $tables = [
            'laboratoires','pansements','pediatries','smi','consultations',
            'sanitaires','kinesitherapies','gynecologies','maternites',
            'arus','bloc_operatoires','medecines',
        ];
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table,'service_id')) {
                Schema::table($table, function (Blueprint $t) {
                    // suivant ta version de Laravel/MySQL, adapte le drop :
                    $t->dropConstrainedForeignId('service_id'); // ou dropForeign + dropColumn
                });
            }
        }
    }
};
