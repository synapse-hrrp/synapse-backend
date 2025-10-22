<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('factures', function (Blueprint $table) {
      $table->string('service_slug')->nullable()->after('patient_id');
      $table->index(['patient_id','service_slug','statut']);
    });
  }
  public function down(): void {
    Schema::table('factures', function (Blueprint $table) {
      $table->dropIndex(['patient_id','service_slug','statut']);
      $table->dropColumn('service_slug');
    });
  }
};
