<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('services')->where('slug','medecine')->update([
            'config' => json_encode([
                'detail_model' => \App\Models\Medecine::class,
                'detail_fk' => 'visite_id',
                'detail_doctor_field' => 'soignant_id',
                'require_doctor_for_detail' => true,
                'detail_defaults' => ['statut' => 'en_cours'],
            ], JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function down(): void
    {
        DB::table('services')->where('slug','medecine')
          ->update(['config' => json_encode(new stdClass())]); // {}
    }
};
