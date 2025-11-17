<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $table = 'reglements';

    private function indexExists(string $index): bool
    {
        $dbName = DB::getDatabaseName();
        $sql = "SELECT COUNT(1) AS c
                FROM information_schema.statistics
                WHERE table_schema = ? AND table_name = ? AND index_name = ?";
        $row = DB::selectOne($sql, [$dbName, $this->table, $index]);
        return (int)($row->c ?? 0) > 0;
    }

    public function up(): void
    {
        $tableName = $this->table;

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'created_at') && ! $this->indexExists('regl_created_at_idx')) {
                $table->index('created_at', 'regl_created_at_idx');
            }

            if (Schema::hasColumn($tableName, 'service_id') && ! $this->indexExists('regl_service_id_idx')) {
                $table->index('service_id', 'regl_service_id_idx');
            }

            if (Schema::hasColumn($tableName, 'cashier_id') && ! $this->indexExists('regl_cashier_id_idx')) {
                $table->index('cashier_id', 'regl_cashier_id_idx');
            }

            if (Schema::hasColumn($tableName, 'cash_session_id') && ! $this->indexExists('regl_session_id_idx')) {
                $table->index('cash_session_id', 'regl_session_id_idx');
            }

            if (Schema::hasColumn($tableName, 'workstation') && ! $this->indexExists('regl_workstation_idx')) {
                $table->index('workstation', 'regl_workstation_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            if ($this->indexExists('regl_created_at_idx')) {
                $table->dropIndex('regl_created_at_idx');
            }
            if ($this->indexExists('regl_service_id_idx')) {
                $table->dropIndex('regl_service_id_idx');
            }
            if ($this->indexExists('regl_cashier_id_idx')) {
                $table->dropIndex('regl_cashier_id_idx');
            }
            if ($this->indexExists('regl_session_id_idx')) {
                $table->dropIndex('regl_session_id_idx');
            }
            if ($this->indexExists('regl_workstation_idx')) {
                $table->dropIndex('regl_workstation_idx');
            }
        });
    }
};
