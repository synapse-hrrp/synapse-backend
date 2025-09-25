<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\Visite;

class Pansement extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'pansements';

    // ⚠️ on NE met PAS soignant_id ici: source = visites.medecin_id (Personnel)
    protected $fillable = [
        'patient_id','visite_id','service_id', // ← service_id si colonne existante
        'date_soin','type','observation','etat_plaque','produits_utilises',
        'status',
    ];

    protected $casts = [
        'date_soin' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!$m->id)        $m->id = (string) Str::uuid();
            if (!$m->date_soin) $m->date_soin = now();
            if (!$m->status)    $m->status = 'en_cours';

            if ($m->visite_id) {
                $v = Visite::query()->select(['patient_id','service_id','medecin_id'])->find($m->visite_id);
                if ($v) {
                    $m->patient_id  = $m->patient_id ?: $v->patient_id;
                    $m->service_id  = $m->service_id ?: $v->service_id;
                    $m->soignant_id = $v->medecin_id; // 🔒 soignant = Personnel
                }
            }
        });

        static::updating(function (self $m) {
            if ($m->isDirty('visite_id') || empty($m->soignant_id) || empty($m->service_id) || empty($m->patient_id)) {
                if ($m->visite_id) {
                    $v = Visite::query()->select(['patient_id','service_id','medecin_id'])->find($m->visite_id);
                    if ($v) {
                        $m->patient_id  = $m->patient_id ?: $v->patient_id;
                        $m->service_id  = $m->service_id ?: $v->service_id;
                        $m->soignant_id = $v->medecin_id ?: $m->soignant_id;
                    }
                }
            }
        });
    }

    // Relations
    public function patient()  { return $this->belongsTo(Patient::class); }
    public function visite()   { return $this->belongsTo(Visite::class); }
    public function soignant() { return $this->belongsTo(\App\Models\Personnel::class, 'soignant_id'); } // ✅
    public function service()  { return $this->belongsTo(\App\Models\Service::class); } // si colonne service_id
}
