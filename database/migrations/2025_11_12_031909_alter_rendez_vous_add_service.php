<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('rendez_vous', function (Blueprint $t) {
            $t->string('service_slug')->after('medecin_id');
            $t->foreign('service_slug')->references('slug')->on('services');
            $t->unsignedBigInteger('tarif_id')->nullable()->after('service_slug');
            $t->index(['service_slug','date']);
        });
    }
    public function down(): void {
        Schema::table('rendez_vous', function (Blueprint $t) {
            $t->dropForeign(['service_slug']);
            $t->dropColumn(['service_slug','tarif_id']);
        });
    }
};
