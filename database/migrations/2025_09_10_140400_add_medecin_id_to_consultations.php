<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $t) {
            if (!Schema::hasColumn('consultations','medecin_id')) {
                $t->unsignedBigInteger('medecin_id')->nullable()->after('soignant_id');
                $t->foreign('medecin_id')->references('id')->on('users');
            }
        });
    }
    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $t) {
            if (Schema::hasColumn('consultations','medecin_id')) {
                $t->dropForeign(['medecin_id']);
                $t->dropColumn('medecin_id');
            }
        });
    }
};
