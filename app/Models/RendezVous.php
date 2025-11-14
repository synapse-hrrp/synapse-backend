<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class RendezVous extends Model
{
    use HasUuids;

    protected $table = 'rendez_vous';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'medecin_id','patient_id','service_slug','tarif_id',
        'date','start_time','end_time','status','source','notes','cancel_reason'
    ];

    protected $casts = ['date'=>'date'];

    public function medecin(){ return $this->belongsTo(Medecin::class); }
    public function patient(){ return $this->belongsTo(Patient::class); }
    public function service(){ return $this->belongsTo(Service::class, 'service_slug', 'slug'); }

    // Scopes utiles
    public function scopeForDate($q, $date){ return $q->whereDate('date',$date); }
    public function scopeConfirmed($q){ return $q->where('status','confirmed'); }
}
