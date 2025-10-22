<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tarif;

class TarifSeeder extends Seeder
{
    public function run(): void
    {
        $tarifs = [
            ['code' => 'ACT001', 'libelle' => 'Consultation générale', 'montant' => 2000, 'service_slug' => 'medecine'],
            ['code' => 'ACT002', 'libelle' => 'Consultation pédiatrique', 'montant' => 2500, 'service_slug' => 'pediatrie'],
            ['code' => 'ACT003', 'libelle' => 'Pansement simple', 'montant' => 1000, 'service_slug' => 'pansement'],
            ['code' => 'ACT004', 'libelle' => 'Accouchement normal', 'montant' => 25000, 'service_slug' => 'maternite'],
            ['code' => 'ACT005', 'libelle' => 'Consultation gynécologique', 'montant' => 3000, 'service_slug' => 'gynecologie'],
            ['code' => 'ACT006', 'libelle' => 'Analyse sanguine complète', 'montant' => 5000, 'service_slug' => 'laboratoire'],
            ['code' => 'ACT007', 'libelle' => 'Radiographie thoracique', 'montant' => 7000, 'service_slug' => 'maternite'],
            ['code' => 'ACT008', 'libelle' => 'Injection intramusculaire', 'montant' => 1500, 'service_slug' => 'pansement'],
            ['code' => 'ACT009', 'libelle' => 'Hospitalisation journalière', 'montant' => 10000, 'service_slug' => 'medecine'],
            ['code' => 'ACT010', 'libelle' => 'Consultation d’urgence', 'montant' => 4000, 'service_slug' => 'aru'],
        ];

        foreach ($tarifs as $t) {
            Tarif::firstOrCreate(
                ['code' => $t['code']],
                [
                    'libelle' => $t['libelle'],
                    'montant' => $t['montant'],
                    'devise' => 'XAF',
                    'is_active' => true,
                    'service_slug' => $t['service_slug'],
                ]
            );
        }
    }
}
