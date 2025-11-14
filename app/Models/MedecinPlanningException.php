<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedecinPlanningException extends Model
{
    protected $fillable = [
        'medecin_id','date','is_working','start_time','end_time',
        'slot_duration','capacity_per_slot','reason'
    ];

    protected $casts = ['date'=>'date','is_working'=>'boolean'];

    public function medecin(){ return $this->belongsTo(Medecin::class); }
}
