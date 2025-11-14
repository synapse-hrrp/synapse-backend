<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Models\RendezVous;
use App\Models\Medecin;
use App\Models\Patient;
use App\Models\Service;

class RendezVousSeeder extends Seeder
{
    public function run(): void
    {
        $startDate = Carbon::now()->startOfWeek(); // lundi de cette semaine
        $endDate   = (clone $startDate)->addDays(6);

        $service = Service::where('slug','consultation')->first();
        if (!$service) return;

        $patients = Patient::inRandomOrder()->take(40)->get();

        Medecin::all()->each(function ($med) use ($startDate,$endDate,$patients,$service) {
            $day = (clone $startDate);
            while ($day <= $endDate) {
                // prennez 3 à 6 patients par jour au hasard
                $toBook = rand(3,6);
                $slotStart = Carbon::parse($day->toDateString().' 08:00');
                for ($i=0; $i<$toBook; $i++) {
                    $p = $patients->random();
                    // 20 min slot par défaut (overrides possibles au pivot)
                    RendezVous::create([
                        'medecin_id'   => $med->id,
                        'patient_id'   => $p->id,
                        'service_slug' => $service->slug,
                        'date'         => $day->toDateString(),
                        'start_time'   => $slotStart->format('H:i'),
                        'end_time'     => $slotStart->copy()->addMinutes(20)->format('H:i'),
                        'status'       => $i < 4 ? 'confirmed' : 'pending', // quelques confirmés
                        'source'       => 'seed',
                    ]);
                    $slotStart->addMinutes(20);
                }
                $day->addDay();
            }
        });
    }
}
