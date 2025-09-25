<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\Visite;

class Gynecologie extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'gynecologies';

    // ⚠️ on NE met PAS soignant_id ici: source = visites.medecin_id (Personnel)
    protected $fillable = [
        'patient_id','visite_id','service_id', // ← ajoute service_id si la colonne existe
        'date_acte',
        'motif','diagnostic','examen_clinique','traitements','observation',
        'tension_arterielle','temperature','frequence_cardiaque','frequence_respiratoire',
        'statut',
    ];

    protected $casts = [
        'date_acte'   => 'datetime',
        'temperature' => 'decimal:1',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!$m->id)        $m->id = (string) Str::uuid();
            if (!$m->date_acte) $m->date_acte = now();
            if (!$m->statut)    $m->statut = 'en_cours';

            if ($m->visite_id) {
                $v = Visite::query()->select(['patient_id','service_id','medecin_id'])->find($m->visite_id);
                if ($v) {
                    $m->patient_id  = $m->patient_id ?: $v->patient_id;
                    $m->service_id  = $m->service_id ?: $v->service_id;
                    $m->soignant_id = $v->medecin_id; // 🔒 Personnel de la visite
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

    public function patient()  { return $this->belongsTo(\App\Models\Patient::class); }
    public function visite()   { return $this->belongsTo(\App\Models\Visite::class); } // contient medecin_id/service_id
    public function soignant() { return $this->belongsTo(\App\Models\Personnel::class, 'soignant_id'); } // ✅ Personnel
    public function service()  { return $this->belongsTo(\App\Models\Service::class); } // si colonne service_id
}
