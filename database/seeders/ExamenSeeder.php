<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Examen;
use Carbon\Carbon;

class ExamenSeeder extends Seeder
{
    public function run(): void
    {
        // Exemple 1 : Examen créé depuis le Labo (sans service)
        Examen::create([
            'id'                  => (string) Str::uuid(),
            'patient_id'          => '3514b2da-ca92-4f9a-ad69-d1557a3d92ed',
            'service_slug'        => null, // pas de service
            'type_origine'        => 'externe',
            'prescripteur_externe'=> 'Dr. Mba Laurent',
            'reference_demande'   => 'EXT-REQ-'.rand(1000,9999),

            'created_via'         => 'labo',
            'created_by_user_id'  => 1,

            'code_examen'         => 'HEM01',
            'nom_examen'          => 'Hémogramme complet',
            'prelevement'         => 'Sang total (EDTA)',
            'statut'              => 'en_attente',

            'valeur_resultat'     => null,
            'unite'               => '',
            'intervalle_reference'=> '',
            'resultat_json'       => null,

            'prix'                => 5000,
            'devise'              => 'XAF',
            'facture_id'          => null,

            'demande_par'         => null,
            'date_demande'        => Carbon::now()->subDays(2),

            'valide_par'          => null,
            'date_validation'     => null,
        ]);

        // Exemple 2 : Examen créé depuis un service interne
        Examen::create([
            'id'                  => (string) Str::uuid(),
            'patient_id'          => '3514b2da-ca92-4f9a-ad69-d1557a3d92ed',
            'service_slug'        => 'medecine', // un slug existant dans ta table services
            'type_origine'        => 'interne',
            'prescripteur_externe'=> null,
            'reference_demande'   => 'INT-REQ-'.rand(1000,9999),

            'created_via'         => 'service',
            'created_by_user_id'  => 2,

            'code_examen'         => 'ECG01',
            'nom_examen'          => 'Électrocardiogramme (ECG)',
            'prelevement'         => 'Appareil ECG',
            'statut'              => 'valide',

            'valeur_resultat'     => 'Rythme sinusal normal',
            'unite'               => '',
            'intervalle_reference'=> '',
            'resultat_json'       => [
                'frequence' => 75,
                'commentaire' => 'Aucune anomalie détectée'
            ],

            'prix'                => 15000,
            'devise'              => 'XAF',
            'facture_id'          => 3,

            'demande_par'         => 4, // id du personnel demandeur
            'date_demande'        => Carbon::now()->subDays(1),

            'valide_par'          => 2, // id du personnel validateur
            'date_validation'     => Carbon::now(),
        ]);
    }
}
