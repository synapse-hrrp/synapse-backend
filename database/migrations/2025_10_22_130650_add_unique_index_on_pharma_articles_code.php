<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pharma_articles', function (Blueprint $table) {
            if (!Schema::hasColumn('pharma_articles', 'code')) return;

            $indexes = collect(DB::select("SHOW INDEX FROM pharma_articles"))
                ->pluck('Key_name')
                ->all();

            if (!in_array('pharma_articles_code_unique', $indexes)) {
                $table->unique('code', 'pharma_articles_code_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pharma_articles', function (Blueprint $table) {
            $table->dropUnique('pharma_articles_code_unique');
        });
    }
};
