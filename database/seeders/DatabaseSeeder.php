<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ⚠️ Mets ici uniquement les seeders qui existent VRAIMENT chez toi.
        // L'ordre ci-dessous évite les dépendances cassées et les doublons.
        $this->call([
            // (facultatif) Si tu as un seeder global des rôles/permissions de base
            RolesAndPermissionsSeeder::class,

            // Permissions & rôles spécifiques à la caisse
            CaissePermissionsSeeder::class,

            // Services + comptes associés (+ liaison personnel)
            ServiceSeeder::class,

            // (facultatif) PersonnelSeeder s’il existe séparément
            PersonnelSeeder::class,

            // Seeders Pharma (si présents)
            PharmaStockThresholdSeeder::class,
            PharmaSmartThresholdSeeder::class,
            PharmaQuickSeeder::class,


            ServiceSeeder::class,
            MedecinSeeder::class,
            PatientSeeder::class,
            PlanningSeeder::class,
            RendezVousSeeder::class,
        ]);
    }
}
