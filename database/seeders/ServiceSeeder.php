<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\Service;
use App\Models\Personnel;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Services
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
            Service::firstOrCreate(['slug' => $it['slug']], ['name' => $it['name']]);
        }

        // 2) Rôles (guard 'web')
        $guard = 'web';
        foreach (['reception','medecin','infirmier','laborantin','pharmacien','caissier','gestionnaire','admin','superuser'] as $r) {
            Role::firstOrCreate(['name'=>$r, 'guard_name'=>$guard]);
        }

        // 3) Comptes par service (+ liaison Personnel)
        $map = [
            'accueil'        => ['Reception',       'reception',   'accueil@hopital.cg',     '+242060000010'],
            'consultations'  => ['Consultations',   'medecin',     'consultations@hopital.cg','+242060000020'],
            'medecine'       => ['Médecine',        'medecin',     'medecine@hopital.cg',    '+242060000021'],
            'aru'            => ['ARU',             'infirmier',   'aru@hopital.cg',         '+242060000022'],
            'laboratoire'    => ['Laboratoire',     'laborantin',  'laboratoire@hopital.cg', '+242060000011'],
            'pharmacie'      => ['Pharmacie',       'pharmacien',  'pharmacie@hopital.cg',   '+242060000012'],
            'finance'        => ['Finance',         'caissier',    'finance@hopital.cg',     '+242060000013'],
            'logistique'     => ['Logistique',      'gestionnaire','logistique@hopital.cg',  '+242060000014'],
            'pansement'      => ['Pansement',       'infirmier',   'pansement@hopital.cg',   '+242060000015'],
            'kinesitherapie' => ['Kinésithérapie',  'infirmier',   'kine@hopital.cg',        '+242060000016'],
            'gestion-malade' => ['Gestion Malade',  'gestionnaire','gestion-malade@hopital.cg','+242060000017'],
            'sanitaire'      => ['Sanitaire',       'gestionnaire','sanitaire@hopital.cg',   '+242060000018'],
            'gynecologie'    => ['Gynécologie',     'medecin',     'gynecologie@hopital.cg', '+242060000019'],
            'maternite'      => ['Maternité',       'infirmier',   'maternite@hopital.cg',   '+242060000023'],
            'pediatrie'      => ['Pédiatrie',       'medecin',     'pediatrie@hopital.cg',   '+242060000024'],
            'smi'            => ['SMI',             'infirmier',   'smi@hopital.cg',         '+242060000025'],
            'bloc-operatoire'=> ['Bloc Opératoire', 'gestionnaire','bloc@hopital.cg',        '+242060000026'],
            'statistiques'   => ['Statistiques',    'gestionnaire','stats@hopital.cg',       '+242060000027'],
            'pourcentage'    => ['Pourcentages',    'gestionnaire','pourcentage@hopital.cg', '+242060000028'],
            'personnel'      => ['Personnel',       'gestionnaire','personnel@hopital.cg',   '+242060000029'],
        ];

        foreach ($map as $slug => [$label, $role, $email, $phone]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $label.' Agent',
                    'password' => Hash::make('ChangeMoi#2025'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'phone' => $phone,
                ]
            );

            $user->syncRoles([$role]);

            $service = Service::where('slug', $slug)->first();
            if ($service) {
                Personnel::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'first_name' => $label,
                        'last_name'  => 'Agent',
                        'job_title'  => Str::title($role),
                        'service_id' => $service->id,
                    ]
                );
            }
        }
    }
}
