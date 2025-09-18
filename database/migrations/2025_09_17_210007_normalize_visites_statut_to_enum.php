<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) On enlève l'ENUM pour éviter l'erreur et on garde les données telles quelles
        DB::statement("ALTER TABLE visites MODIFY statut VARCHAR(32) NOT NULL");

        // 2) Normaliser / mapper les anciennes valeurs vers les nouvelles
        //    Adapte si tu vois d'autres variantes dans ta base.
        DB::statement("UPDATE visites SET statut = TRIM(statut)");

        // minuscules/espaces -> MAJ
        DB::statement("UPDATE visites SET statut = UPPER(statut)");

        // Mappings fréquents depuis ton ancien schéma
        DB::statement("UPDATE visites SET statut = 'EN_ATTENTE' WHERE statut IN ('EN COURS','EN_COURS','ATTENTE','', 'ENATTENTE')");
        DB::statement("UPDATE visites SET statut = 'CLOTUREE'   WHERE statut IN ('CLOS','CLOTURE','CLOTURÉ','CLOTURÉE','FERME','FERMEE')");
        DB::statement("UPDATE visites SET statut = 'PAYEE'      WHERE statut IN ('PAYE','PAYÉ','PAYÉE','PAYEE')");

        // 'ANNULE' n'existe pas dans ta nouvelle enum -> on le rapproche de CLOTUREE (ou choisis une autre règle)
        DB::statement("UPDATE visites SET statut = 'CLOTUREE'   WHERE statut IN ('ANNULE','ANNULÉ','ANNULÉE','ANNULEE')");

        // Filets de sécurité : toute valeur hors liste -> EN_ATTENTE
        DB::statement("
            UPDATE visites
            SET statut = 'EN_ATTENTE'
            WHERE statut NOT IN ('EN_ATTENTE','A_ENCAISSER','PAYEE','CLOTUREE')
        ");

        // 3) Reposer l'ENUM définitif
        DB::statement("
            ALTER TABLE visites
            MODIFY statut ENUM('EN_ATTENTE','A_ENCAISSER','PAYEE','CLOTUREE')
            NOT NULL DEFAULT 'EN_ATTENTE'
        ");
    }

    public function down(): void
    {
        // Revenir à un VARCHAR simple (adapte si tu avais un autre enum avant)
        DB::statement("ALTER TABLE visites MODIFY statut VARCHAR(32) NOT NULL");
    }
};
