<?php
// database/migrations/2025_09_24_110541_alter_arus_soignant_fk_to_personnels.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 0) Drop le(s) FK existant(s) sur arus.soignant_id (quel que soit le nom)
        $fkRows = DB::select("
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'arus'
              AND COLUMN_NAME = 'soignant_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        foreach ($fkRows as $row) {
            $name = $row->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE `arus` DROP FOREIGN KEY `$name`");
        }

        // 1) S'assurer que le type de colonne matche personnels.id
        //    BIGINT UNSIGNED NULL (temporaire)
        //    Si UUID chez toi, remplace par:
        //    DB::statement("ALTER TABLE `arus` MODIFY `soignant_id` CHAR(36) NULL");
        DB::statement("ALTER TABLE `arus` MODIFY `soignant_id` BIGINT UNSIGNED NULL");

        // 2) REMAP users.id -> personnels.id via personnels.user_id (si ancien schéma)
        //    Cette requête est safe même s'il n'y a pas de correspondance.
        DB::statement("
            UPDATE arus a
            JOIN personnels p ON p.user_id = a.soignant_id
            SET a.soignant_id = p.id
        ");

        // 3) Compléter depuis visites.medecin_id quand soignant_id est encore NULL
        DB::statement("
            UPDATE arus a
            JOIN visites v ON v.id = a.visite_id
            SET a.soignant_id = v.medecin_id
            WHERE a.soignant_id IS NULL AND v.medecin_id IS NOT NULL
        ");

        // 4) Ajouter le NOUVEAU FK vers personnels(id) en SET NULL (tolérant)
        Schema::table('arus', function (Blueprint $table) {
            $table->foreign('soignant_id')
                  ->references('id')->on('personnels')
                  ->nullOnDelete(); // ON DELETE SET NULL
        });

        // 5) Log si données encore orphelines (FK en SET NULL permet d'avancer quand même)
        $orphans = DB::table('arus as a')
            ->leftJoin('personnels as p','p.id','=','a.soignant_id')
            ->whereNotNull('a.soignant_id')
            ->whereNull('p.id')
            ->count();

        if ($orphans > 0) {
            logger()->warning("[FK arus.soignant_id->personnels.id] {$orphans} enregistrements orphelins détectés après remap.");
        }
    }

    public function down(): void
    {
        // Drop le FK actuel (quel que soit son nom)
        $fkRows = DB::select("
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'arus'
              AND COLUMN_NAME = 'soignant_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        foreach ($fkRows as $row) {
            $name = $row->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE `arus` DROP FOREIGN KEY `$name`");
        }

        // (Optionnel) revenir au type précédent si besoin.
        // Ex si c'était users.id (bigint):
        DB::statement("ALTER TABLE `arus` MODIFY `soignant_id` BIGINT UNSIGNED NULL");

        // Et rétablir l'ancien FK si tu veux (adapté à ton ancien schéma) :
        // Schema::table('arus', function (Blueprint $table) {
        //     $table->foreign('soignant_id')->references('id')->on('users')->nullOnDelete();
        // });
    }
};
