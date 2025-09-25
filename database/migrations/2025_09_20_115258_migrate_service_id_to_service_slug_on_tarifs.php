<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Ajouter la nouvelle colonne (temporairement nullable)
        Schema::table('tarifs', function (Blueprint $table) {
            $table->string('service_slug')->nullable()->after('is_active');
        });

        // 2) Renseigner service_slug à partir de service_id
        // Méthode rapide (SQL) – OK pour MySQL/MariaDB et PostgreSQL
        // MySQL/MariaDB :
        // DB::statement('UPDATE tarifs t JOIN services s ON s.id = t.service_id SET t.service_slug = s.slug');

        // PostgreSQL :
        // DB::statement('UPDATE tarifs t SET service_slug = s.slug FROM services s WHERE s.id = t.service_id');

        // Si tu veux rester “agnostique” via Query Builder (MySQL/MariaDB) :
        DB::table('tarifs')
            ->join('services', 'services.id', '=', 'tarifs.service_id')
            ->update(['tarifs.service_slug' => DB::raw('services.slug')]);

        // 3) Vérifier les lignes problématiques (optionnel mais recommandé)
        // - lignes sans service_id
        $sansId = DB::table('tarifs')->whereNull('service_id')->count();
        // - lignes où le join n’a pas trouvé de service (slug resté NULL)
        $sansSlug = DB::table('tarifs')->whereNull('service_slug')->count();

        if ($sansSlug > 0) {
            // À toi de décider : soit on bloque, soit on met un slug par défaut, soit on désactive ces tarifs, etc.
            // Ici on bloque proprement pour éviter d’avoir une contrainte NOT NULL qui casse :
            throw new \RuntimeException("Migration interrompue : {$sansSlug} tarif(s) n'ont pas pu être rattachés à un service (slug manquant). Corrige d'abord les données.");
        }

        // 4) Rendre la colonne NOT NULL + ajouter la contrainte et l’index
        Schema::table('tarifs', function (Blueprint $table) {
            $table->string('service_slug')->nullable(false)->change();
            $table->foreign('service_slug')->references('slug')->on('services')->cascadeOnDelete();
            $table->index('service_slug');
        });

        // 5) Supprimer l’ancienne FK + colonne service_id
        Schema::table('tarifs', function (Blueprint $table) {
            // Si une contrainte existe déjà, son nom peut varier. La forme ci-dessous marche si la FK s’appelle implicitement.
            try { $table->dropForeign(['service_id']); } catch (\Throwable $e) {}
            $table->dropColumn('service_id');
        });
    }

    public function down(): void
    {
        // Le down remet service_id et enlève service_slug
        Schema::table('tarifs', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable()->after('is_active');
        });

        // Re-remplir service_id depuis le slug
        // MySQL/MariaDB :
        // DB::statement('UPDATE tarifs t JOIN services s ON s.slug = t.service_slug SET t.service_id = s.id');
        // PostgreSQL :
        // DB::statement('UPDATE tarifs t SET service_id = s.id FROM services s WHERE s.slug = t.service_slug');

        DB::table('tarifs')
            ->join('services', 'services.slug', '=', 'tarifs.service_slug')
            ->update(['tarifs.service_id' => DB::raw('services.id')]);

        Schema::table('tarifs', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable(false)->change();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
        });

        Schema::table('tarifs', function (Blueprint $table) {
            try { $table->dropForeign(['service_slug']); } catch (\Throwable $e) {}
            $table->dropIndex(['service_slug']);
            $table->dropColumn('service_slug');
        });
    }
};
