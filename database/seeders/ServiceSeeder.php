<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['slug'=>'accueil', 'name'=>'Accueil / Réception'],
            ['slug'=>'consultations',   'name'=>'Consultations'],
            ['slug'=>'medecine',        'name'=>'Médecine Générale'],
            ['slug'=>'aru',             'name'=>'Accueil & Urgences (ARU)'],
            ['slug'=>'laboratoire',     'name'=>'Laboratoire'],
            ['slug'=>'pharmacie',       'name'=>'Pharmacie'],
            ['slug'=>'finance',         'name'=>'Caisse / Finance'],
            ['slug'=>'logistique',      'name'=>'Logistique'],
            ['slug'=>'pansement',       'name'=>'Pansement'],
            ['slug'=>'kinesitherapie',  'name'=>'Kinésithérapie'],
            ['slug'=>'gestion-malade',  'name'=>'Gestion des Malades (Hospitalisation)'],
            ['slug'=>'sanitaire',       'name'=>'Programme Sanitaire (Tuberculose/VIH)'],
            ['slug'=>'gynecologie',     'name'=>'Gynécologie'],
            ['slug'=>'maternite',       'name'=>'Maternité'],
            ['slug'=>'pediatrie',       'name'=>'Pédiatrie'],
            ['slug'=>'smi',             'name'=>'SMI (Santé Maternelle & Infantile)'],
            ['slug'=>'bloc-operatoire', 'name'=>'Bloc Opératoire'],
            ['slug'=>'statistiques',    'name'=>'Statistiques / Dashboard'],
            ['slug'=>'pourcentage',     'name'=>'Répartition des Pourcentages'],
            ['slug'=>'personnel',       'name'=>'Gestion du Personnel'],
        ];

        foreach ($items as $it) {
            Service::firstOrCreate(['slug'=>$it['slug']], $it);
        }
    }
}
