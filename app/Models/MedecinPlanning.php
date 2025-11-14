<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedecinPlanning extends Model
{
    protected $fillable = [
        'medecin_id','weekday','start_time','end_time',
        'slot_duration','capacity_per_slot','is_active'
    ];

    public function medecin(){ return $this->belongsTo(Medecin::class); }
}
