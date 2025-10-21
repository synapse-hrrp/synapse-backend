<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('personnels', function (Blueprint $table) {
            // 1) S'assurer que deleted_at existe (au cas où la migration d'ajout n'a pas été jouée)
            if (! Schema::hasColumn('personnels', 'deleted_at')) {
                $table->softDeletes();
            }

            // 2) Remplacer l'unique simple par un unique composite
            //    ⚠️ Le nom auto-généré par Laravel pour l'unique est généralement "personnels_matricule_unique"
            //    Si tu avais un nom personnalisé, remplace-le ci-dessous.
            $table->dropUnique('personnels_matricule_unique');

            // Crée un index unique composite (matricule, deleted_at)
            $table->unique(['matricule', 'deleted_at'], 'personnels_matricule_deleted_at_unique');
        });
    }

    public function down(): void {
        Schema::table('personnels', function (Blueprint $table) {
            // Revenir à l'état initial : unique simple sur matricule
            $table->dropUnique('personnels_matricule_deleted_at_unique');
            $table->unique('matricule', 'personnels_matricule_unique');

            // (Optionnel) ne pas supprimer deleted_at ici : d'autres parties de l'app peuvent l'utiliser.
            // Si tu veux VRAIMENT revenir en arrière totalement :
            // if (Schema::hasColumn('personnels', 'deleted_at')) {
            //     $table->dropSoftDeletes();
            // }
        });
    }
};
