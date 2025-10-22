<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Service;
use App\Models\Consultation;

class ServiceConfigSeeder extends Seeder
{
    public function run(): void
    {
        // Configuration par service
        $perSlug = [
            'consultation' => [
                'detail_model'            => Consultation::class,
                'detail_fk'               => 'visite_id',
                'detail_doctor_field'     => 'soignant_id',
                'require_doctor_for_detail'=> true,
                'detail_defaults'         => ['statut' => 'en_cours'],
            ],
            'kinesitherapie' => [
                'detail_model'            => Consultation::class,
                'detail_fk'               => 'visite_id',
                'detail_doctor_field'     => 'soignant_id',
                'require_doctor_for_detail'=> true,
                'detail_defaults'         => ['statut' => 'en_cours'],
            ],
            'pediatrie' => [
                'detail_model'            => Consultation::class,
                'detail_fk'               => 'visite_id',
                'detail_doctor_field'     => 'soignant_id',
                'require_doctor_for_detail'=> true,
                'detail_defaults'         => ['statut' => 'en_cours'],
            ],
            'gynecologie' => [
                'detail_model'            => Consultation::class,
                'detail_fk'               => 'visite_id',
                'detail_doctor_field'     => 'soignant_id',
                'require_doctor_for_detail'=> true,
                'detail_defaults'         => ['statut' => 'en_cours'],
            ],
            'maternite' => [
                'detail_model'            => Consultation::class,
                'detail_fk'               => 'visite_id',
                'detail_doctor_field'     => 'soignant_id',
                'require_doctor_for_detail'=> true,
                'detail_defaults'         => ['statut' => 'en_cours'],
            ],
            'medecine' => [
                'detail_model'            => Consultation::class,
                'detail_fk'               => 'visite_id',
                'detail_doctor_field'     => 'soignant_id',
                'require_doctor_for_detail'=> true,
                'detail_defaults'         => ['statut' => 'en_cours'],
            ],
            'sanitaire' => [
                'detail_model'            => Consultation::class,
                'detail_fk'               => 'visite_id',
                'detail_doctor_field'     => 'soignant_id',
                'require_doctor_for_detail'=> true,
                'detail_defaults'         => ['statut' => 'en_cours'],
            ],
            'smi' => [
                'detail_model'            => Consultation::class,
                'detail_fk'               => 'visite_id',
                'detail_doctor_field'     => 'soignant_id',
                'require_doctor_for_detail'=> true,
                'detail_defaults'         => ['statut' => 'en_cours'],
            ],
            'aru' => [
                'detail_model'            => Consultation::class,
                'detail_fk'               => 'visite_id',
                'detail_doctor_field'     => 'soignant_id',
                'require_doctor_for_detail'=> true,
                'detail_defaults'         => ['statut' => 'en_cours'],
            ],
            'pansement' => [
                'detail_model'            => Consultation::class,
                'detail_fk'               => 'visite_id',
                'detail_doctor_field'     => 'soignant_id',
                'require_doctor_for_detail'=> true,
                'detail_defaults'         => ['statut' => 'en_cours'],
            ],
        ];

        DB::transaction(function () use ($perSlug) {
            Service::query()
                ->whereIn('slug', array_keys($perSlug))
                ->each(function (Service $s) use ($perSlug) {
                    $existing = $s->config ?? [];
                    // fusion idempotente : garde les clés existantes
                    $merged = $perSlug[$s->slug] + $existing;
                    $s->config = $merged;
                    $s->save();
                });
        });
    }
}
