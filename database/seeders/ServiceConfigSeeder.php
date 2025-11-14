<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Service;

class ServiceConfigSeeder extends Seeder
{
    public function run(): void
    {
        // Slugs EXACTS du ServiceSeeder
        $perSlug = [
            // pas de detail_model ici -> l'Observer tentera App\Models\{StudlySlug}, sinon fallback
            'consultations' => [
                'detail_fk'                 => 'visite_id',
                'detail_doctor_field'       => 'soignant_id', // si la colonne existe
                'require_doctor_for_detail' => false,
                'detail_defaults'           => ['statut' => 'en_cours'],
            ],
            'medecine' => [
                'detail_fk'                 => 'visite_id',
                'detail_doctor_field'       => 'soignant_id',
                'require_doctor_for_detail' => false,
                'detail_defaults'           => ['statut' => 'en_cours'],
            ],
            'gynecologie' => [
                'detail_fk'                 => 'visite_id',
                'detail_doctor_field'       => 'soignant_id',
                'require_doctor_for_detail' => false,
                'detail_defaults'           => ['statut' => 'en_cours'],
            ],
            'maternite' => [
                'detail_fk'                 => 'visite_id',
                'detail_doctor_field'       => 'soignant_id',
                'require_doctor_for_detail' => false,
                'detail_defaults'           => ['statut' => 'en_cours'],
            ],
            'pediatrie' => [
                'detail_fk'                 => 'visite_id',
                'detail_doctor_field'       => 'soignant_id',
                'require_doctor_for_detail' => false,
                'detail_defaults'           => ['statut' => 'en_cours'],
            ],
            'kinesitherapie' => [
                'detail_fk'                 => 'visite_id',
                'detail_doctor_field'       => 'soignant_id',
                'require_doctor_for_detail' => false,
                'detail_defaults'           => ['statut' => 'en_cours'],
            ],
            'pansement' => [
                'detail_fk'                 => 'visite_id',
                'detail_doctor_field'       => 'soignant_id',
                'require_doctor_for_detail' => false,
                'detail_defaults'           => ['statut' => 'en_cours'],
            ],
            'aru' => [
                // ici, SI tu veux forcer le modèle dédié Aru (et que \App\Models\Aru existe), tu peux l’indiquer:
                // 'detail_model'            => \App\Models\Aru::class,
                'detail_fk'                 => 'visite_id',
                'detail_doctor_field'       => 'soignant_id',
                'require_doctor_for_detail' => false,
                'detail_defaults'           => ['statut' => 'en_cours'],
            ],
            'sanitaire' => [
                'detail_fk'                 => 'visite_id',
                'detail_doctor_field'       => 'soignant_id',
                'require_doctor_for_detail' => false,
                'detail_defaults'           => ['statut' => 'en_cours'],
            ],
            'smi' => [
                'detail_fk'                 => 'visite_id',
                'detail_doctor_field'       => 'soignant_id',
                'require_doctor_for_detail' => false,
                'detail_defaults'           => ['statut' => 'en_cours'],
            ],
        ];

        DB::transaction(function () use ($perSlug) {
            Service::query()
                ->whereIn('slug', array_keys($perSlug))
                ->each(function (Service $s) use ($perSlug) {
                    $existing = is_array($s->config ?? null) ? $s->config : [];

                    // La NOUVELLE config (de gauche) prend le dessus sur l’existante
                    $merged = $perSlug[$s->slug] + $existing;

                    $s->config = $merged;
                    $s->save();
                });
        });
    }
}
