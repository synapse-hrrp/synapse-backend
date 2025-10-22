<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Medecin;
use App\Models\Personnel;

class MedecinSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupère tous les personnels
        $personnels = Personnel::all();

        if ($personnels->isEmpty()) {
            $this->command->warn('Aucun personnel trouvé. Crée d’abord des enregistrements dans la table personnels.');
            return;
        }

        // Exemples de spécialités et grades
        $specialites = ['Cardiologie', 'Dermatologie', 'Pédiatrie', 'Neurologie', 'Chirurgie générale'];
        $grades      = ['Assistant', 'Chef de service', 'Professeur', 'Médecin résident'];

        // 👉 Crée des médecins pour (au plus) 10 personnels SANS médecin existant
        $count = 0;
        foreach ($personnels as $personnel) {
            if ($count >= 10) break;

            // Évite les doublons si le seeder est relancé
            if (Medecin::where('personnel_id', $personnel->id)->exists()) {
                continue;
            }

            Medecin::create([
                'personnel_id' => $personnel->id,
                'numero_ordre' => $this->uniqueNumeroOrdre(),
                'specialite'   => fake()->randomElement($specialites),
                'grade'        => fake()->randomElement($grades),
            ]);

            $count++;
        }

        // 👉 Deux exemples "manuels" mais idempotents :
        // On choisit des personnels qui n'ont PAS encore de médecin.
        $availablePersonnelIds = Personnel::whereNotIn('id', Medecin::pluck('personnel_id'))->pluck('id');

        if ($availablePersonnelIds->count() > 0) {
            $p1 = $availablePersonnelIds->shift();
            Medecin::firstOrCreate(
                ['personnel_id' => $p1],
                [
                    'numero_ordre' => $this->uniqueNumeroOrdre('ORD-1001'), // tentera ORD-1001 sinon unique
                    'specialite'   => 'Cardiologie',
                    'grade'        => 'Chef de service',
                ]
            );
        }

        if ($availablePersonnelIds->count() > 0) {
            $p2 = $availablePersonnelIds->shift();
            Medecin::firstOrCreate(
                ['personnel_id' => $p2],
                [
                    'numero_ordre' => $this->uniqueNumeroOrdre('ORD-1002'),
                    'specialite'   => 'Pédiatrie',
                    'grade'        => 'Assistant',
                ]
            );
        }
    }

    /**
     * Génère un numero_ordre unique de type "ORD-XXXX" (ou vérifie un souhaité).
     * Si $preferred est fourni mais déjà pris, on génère un autre code unique.
     */
    private function uniqueNumeroOrdre(?string $preferred = null): string
    {
        // Si un numéro préféré est passé et qu'il est libre, on le prend
        if ($preferred && ! Medecin::where('numero_ordre', $preferred)->exists()) {
            return $preferred;
        }

        // Sinon, on génère de manière sûre un code unique
        do {
            // Ex: ORD-5821 ou ORD-2025-AB12 (si tu préfères plus long, décommente la ligne suivante)
            // $candidate = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
            $candidate = 'ORD-' . fake()->numerify('####');
        } while (Medecin::where('numero_ordre', $candidate)->exists());

        return $candidate;
        // NOTE: si la colonne numero_ordre est UNIQUE en base, c’est parfait.
    }
}
