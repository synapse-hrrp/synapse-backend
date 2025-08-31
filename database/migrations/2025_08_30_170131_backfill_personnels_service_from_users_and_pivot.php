<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::beginTransaction();
        try {
            // 2.1) Copier depuis users.service_id si présent
            if (Schema::hasColumn('users', 'service_id') && Schema::hasColumn('personnels', 'service_id')) {
                $rows = DB::table('users')
                    ->whereNotNull('service_id')
                    ->get(['id as user_id', 'service_id']);

                foreach ($rows as $r) {
                    // Met à jour seulement si la fiche existe (on n'essaie pas de créer une fiche incomplète)
                    DB::table('personnels')
                        ->where('user_id', $r->user_id)
                        ->whereNull('service_id')
                        ->update(['service_id' => $r->service_id]);
                }
            }

            // 2.2) Si pivot service_user existe : priorité à is_primary=1, sinon la plus récente
            if (Schema::hasTable('service_user') && Schema::hasColumn('personnels', 'service_id')) {
                // Primaire
                $primaires = DB::table('service_user')
                    ->select('user_id', 'service_id')
                    ->where('is_primary', 1)
                    ->get();

                foreach ($primaires as $p) {
                    DB::table('personnels')
                        ->where('user_id', $p->user_id)
                        ->whereNull('service_id')
                        ->update(['service_id' => $p->service_id]);
                }

                // Fallback : la plus récente
                $fallbacks = DB::table('service_user')
                    ->select('user_id', 'service_id')
                    ->orderBy('updated_at', 'desc')
                    ->get();

                foreach ($fallbacks as $f) {
                    DB::table('personnels')
                        ->where('user_id', $f->user_id)
                        ->whereNull('service_id')
                        ->update(['service_id' => $f->service_id]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function down(): void
    {
        // Pas de rollback de données.
    }
};
