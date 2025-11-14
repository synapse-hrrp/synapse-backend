<?php

namespace App\Services;

use App\Models\Medecin;
use App\Models\RendezVous;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

class DisponibiliteService
{
    /**
     * Retourne les créneaux disponibles d’un médecin sur une période.
     * Peut filtrer par service ($serviceSlug).
     *
     * @return array [
     *   'days' => [
     *     'YYYY-MM-DD' => [
     *        'slots' => [['start'=>'08:00','end'=>'08:20','remaining'=>1], ...],
     *        'booked_count' => 4,
     *        'confirmable' => true,
     *        'closed' => false
     *     ]
     *   ]
     * ]
     */
    public function slots(Medecin $medecin, Carbon $from, Carbon $to, ?string $serviceSlug = null): array
    {
        $days = [];

        // Pré-chargement RDV par jour (tous services, pour booked_count / confirmable)
        $bookings = RendezVous::where('medecin_id',$medecin->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('status','!=','cancelled')
            ->get()
            ->groupBy(fn($r)=>$r->date->toDateString());

        // Récup pivot service si demandé
        $pivot = null;
        if ($serviceSlug) {
            $pivot = $medecin->services()
                ->where('services.slug', $serviceSlug)
                ->wherePivot('is_active', true)
                ->first();

            // si service ou liaison inactive → aucune dispo
            if (!$pivot || !$pivot->is_active || !($pivot->service?->is_active ?? true)) {
                return ['days'=>[]];
            }
        }

        $period = CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay());
        foreach ($period as $day) {
            $dateStr = $day->toDateString();
            $weekday = (int)$day->isoWeekday(); // 1..7

            // Planning base
            $segments = $medecin->plannings()
                ->where('weekday',$weekday)
                ->where('is_active',true)
                ->get();

            // Exception du jour
            $ex = $medecin->planningExceptions()->whereDate('date',$dateStr)->first();

            $slots = collect();
            if ($ex) {
                if (!$ex->is_working) {
                    $days[$dateStr] = [
                        'slots'=>[],
                        'booked_count' => (int)($bookings[$dateStr]->count() ?? 0),
                        'confirmable' => ($bookings[$dateStr]->count() ?? 0) >= 5,
                        'closed' => true,
                        'reason' => $ex->reason,
                    ];
                    continue;
                }
                // override unique segment
                $segments = collect([ (object)[
                    'start_time' => $ex->start_time,
                    'end_time'   => $ex->end_time,
                    'slot_duration' => $ex->slot_duration
                        ?? optional($pivot)->pivot?->slot_duration
                        ?? optional($segments->first())->slot_duration
                        ?? 20,
                    'capacity_per_slot' => $ex->capacity_per_slot
                        ?? optional($pivot)->pivot?->capacity_per_slot
                        ?? optional($segments->first())->capacity_per_slot
                        ?? 1,
                ]]);
            }

            foreach ($segments as $seg) {
                $slotMin = (int)($seg->slot_duration
                    ?? optional($pivot)->pivot?->slot_duration
                    ?? 20);
                $capacity = (int)($seg->capacity_per_slot
                    ?? optional($pivot)->pivot?->capacity_per_slot
                    ?? 1);

                $start = Carbon::parse($dateStr.' '.$seg->start_time);
                $end   = Carbon::parse($dateStr.' '.$seg->end_time);

                for ($cur = $start->copy(); $cur < $end; $cur->addMinutes($slotMin)) {
                    $slotEnd = $cur->copy()->addMinutes($slotMin);
                    if ($slotEnd > $end) break;

                    // RDV déjà pris sur ce créneau (global, tous services) pour la capacité globale
                    $count = ($bookings[$dateStr] ?? collect())
                        ->where('start_time',$cur->format('H:i:s'))
                        ->count();

                    if ($count < $capacity) {
                        $slots->push([
                            'start' => $cur->format('H:i'),
                            'end'   => $slotEnd->format('H:i'),
                            'remaining' => $capacity - $count,
                        ]);
                    }
                }
            }

            $bookedCount = (int)($bookings[$dateStr]->count() ?? 0);
            $days[$dateStr] = [
                'slots' => $slots->values()->all(),
                'booked_count' => $bookedCount,
                'confirmable' => $bookedCount >= 5,
                'closed' => false,
            ];
        }

        return ['days'=>$days];
    }
}
