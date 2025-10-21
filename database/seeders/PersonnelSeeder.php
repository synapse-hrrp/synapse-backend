<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Service;
use App\Models\Personnel;

class PersonnelSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Normaliser : donner un matricule à ceux qui n'en ont pas
        Personnel::whereNull('matricule')->orWhere('matricule', '')->chunkById(200, function ($batch) {
            foreach ($batch as $p) {
                $p->matricule = $this->generateUniqueMatricule();
                $p->save();
            }
        });

        // 2) Créer un Personnel pour chaque user sans personnel
        User::doesntHave('personnel')->chunkById(200, function ($users) {
            foreach ($users as $user) {
                // Essai : déduire un service depuis la partie avant @ de l'email
                $slugGuess = Str::slug(Str::before($user->email, '@'));
                $service = Service::where('slug', $slugGuess)->first()
                         ?? Service::first(); // fallback simple (ou choisis un slug par défaut)

                Personnel::create([
                    'user_id'    => $user->id,
                    'first_name' => Str::before($user->name, ' ') ?: $user->name,
                    'last_name'  => Str::after($user->name, ' ') ?: 'Agent',
                    'job_title'  => 'Agent',
                    'service_id' => optional($service)->id,
                    'matricule'  => $this->generateUniqueMatricule(),
                ]);
            }
        });
    }

    private function generateUniqueMatricule(): string
    {
        do {
            // Choisis ton format préféré
            $m = 'PER-'.Str::upper(Str::random(8));
        } while (Personnel::where('matricule', $m)->exists());

        return $m;
    }
}
