<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Medecin;
use App\Models\Personnel;
use App\Models\Service;
use App\Models\MedecinPlanning;

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

            $medecin = Medecin::create([
                'personnel_id' => $personnel->id,
                'numero_ordre' => $this->uniqueNumeroOrdre(),
                'specialite'   => fake()->randomElement($specialites),
                'grade'        => fake()->randomElement($grades),
            ]);

            // ➕ Ajouts RDV: lier des services + planning par défaut
            $this->attachServicesAndDefaultPlanning($medecin);

            $count++;
        }

        // 👉 Deux exemples "manuels" mais idempotents :
        // On choisit des personnels qui n'ont PAS encore de médecin.
        $availablePersonnelIds = Personnel::whereNotIn('id', Medecin::pluck('personnel_id'))->pluck('id');

        if ($availablePersonnelIds->count() > 0) {
            $p1 = $availablePersonnelIds->shift();
            $med1 = Medecin::firstOrCreate(
                ['personnel_id' => $p1],
                [
                    'numero_ordre' => $this->uniqueNumeroOrdre('ORD-1001'), // tentera ORD-1001 sinon unique
                    'specialite'   => 'Cardiologie',
                    'grade'        => 'Chef de service',
                ]
            );
            $this->attachServicesAndDefaultPlanning($med1);
        }

        if ($availablePersonnelIds->count() > 0) {
            $p2 = $availablePersonnelIds->shift();
            $med2 = Medecin::firstOrCreate(
                ['personnel_id' => $p2],
                [
                    'numero_ordre' => $this->uniqueNumeroOrdre('ORD-1002'),
                    'specialite'   => 'Pédiatrie',
                    'grade'        => 'Assistant',
                ]
            );
            $this->attachServicesAndDefaultPlanning($med2);
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
            // Ex: ORD-5821
            $candidate = 'ORD-' . fake()->numerify('####');
        } while (Medecin::where('numero_ordre', $candidate)->exists());

        return $candidate;
    }

    /**
     * Attache des services typiques au médecin (+ overrides pivot) et
     * génère un planning par défaut (lun→ven 08–12 / 14–17) s’il est vide.
     */
    private function attachServicesAndDefaultPlanning(Medecin $medecin): void
    {
        // On accepte 'consultation' (singulier) ou 'consultations' (pluriel) selon ta base actuelle
        $consultSlug = Service::whereIn('slug', ['consultation', 'consultations'])->value('slug');
        $vaccinSlug  = Service::where('slug', 'vaccin')->value('slug');
        $panseSlug   = Service::where('slug', 'pansement')->value('slug');

        // Liaison pivot medecin_service (si les services existent)
        $attach = [];
        if ($consultSlug) {
            $attach[$consultSlug] = ['is_active'=>true,'slot_duration'=>20,'capacity_per_slot'=>1];
        }
        if ($vaccinSlug) {
            $attach[$vaccinSlug]  = ['is_active'=>true,'slot_duration'=>10,'capacity_per_slot'=>2];
        }
        if ($panseSlug) {
            $attach[$panseSlug]   = ['is_active'=>true,'slot_duration'=>15,'capacity_per_slot'=>1];
        }

        if (!empty($attach)) {
            $medecin->services()->syncWithoutDetaching($attach);
        }

        // Planning par défaut si vide
        if (!$medecin->plannings()->exists()) {
            $segments = [
                // Lundi(1) à Vendredi(5): matin + aprem (20 min / cap 1 par défaut)
                ['weekday'=>1,'start_time'=>'08:00','end_time'=>'12:00','slot_duration'=>20,'capacity_per_slot'=>1],
                ['weekday'=>1,'start_time'=>'14:00','end_time'=>'17:00','slot_duration'=>20,'capacity_per_slot'=>1],
                ['weekday'=>2,'start_time'=>'08:00','end_time'=>'12:00','slot_duration'=>20,'capacity_per_slot'=>1],
                ['weekday'=>2,'start_time'=>'14:00','end_time'=>'17:00','slot_duration'=>20,'capacity_per_slot'=>1],
                ['weekday'=>3,'start_time'=>'08:00','end_time'=>'12:00','slot_duration'=>20,'capacity_per_slot'=>1],
                ['weekday'=>3,'start_time'=>'14:00','end_time'=>'17:00','slot_duration'=>20,'capacity_per_slot'=>1],
                ['weekday'=>4,'start_time'=>'08:00','end_time'=>'12:00','slot_duration'=>20,'capacity_per_slot'=>1],
                ['weekday'=>4,'start_time'=>'14:00','end_time'=>'17:00','slot_duration'=>20,'capacity_per_slot'=>1],
                ['weekday'=>5,'start_time'=>'08:00','end_time'=>'12:00','slot_duration'=>20,'capacity_per_slot'=>1],
                ['weekday'=>5,'start_time'=>'14:00','end_time'=>'17:00','slot_duration'=>20,'capacity_per_slot'=>1],
            ];
            foreach ($segments as $s) {
                $medecin->plannings()->create($s + ['is_active'=>true]);
            }
        }
    }
}
