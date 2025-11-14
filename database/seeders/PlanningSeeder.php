<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Medecin;
use App\Models\MedecinPlanning;

class PlanningSeeder extends Seeder
{
    public function run(): void
    {
        $segments = [
            // Lundi(1) à Vendredi(5): matin + après-midi
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

        Medecin::all()->each(function ($med) use ($segments) {
            $med->plannings()->delete();
            foreach ($segments as $s) {
                $med->plannings()->create($s + ['is_active'=>true]);
            }
        });
    }
}
