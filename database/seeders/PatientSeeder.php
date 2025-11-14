<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Patient;
use Faker\Factory as Faker;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('fr_FR');

        for ($i=0; $i<50; $i++) {
            Patient::create([
                'numero_dossier' => null, // sera auto généré via boot()
                'nom' => strtoupper($faker->lastName),
                'prenom' => $faker->firstName,
                'date_naissance' => $faker->dateTimeBetween('-70 years','-1 years')->format('Y-m-d'),
                'sexe' => $faker->randomElement(['M','F']),
                'telephone' => $faker->e164PhoneNumber(),
                'adresse' => $faker->streetAddress(),
                'quartier' => $faker->city(),
                'is_active' => true,
            ]);
        }
    }
}
