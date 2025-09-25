<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema; 
use Illuminate\Support\Str;
use App\Models\Visite;

class Smi extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'smis';

    // on NE met PAS soignant_id dans fillable: la source de vérité = visite.medecin_id
    protected $fillable = [
        'patient_id','visite_id', /* 'service_id', */ // si colonne ajoutée
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

            // verrouiller le soignant = medecin de la visite
            if ($m->visite_id && empty($m->soignant_id)) {
                $m->soignant_id = Visite::whereKey($m->visite_id)->value('medecin_id');
            }
            // propager service_id si la colonne existe
            if (Schema::hasColumn('smis','service_id') && $m->visite_id && empty($m->service_id)) {
                $m->service_id = Visite::whereKey($m->visite_id)->value('service_id');
            }
        });

        static::updating(function (self $m) {
            if ($m->isDirty('visite_id') || empty($m->soignant_id)) {
                if ($m->visite_id) {
                    $m->soignant_id = Visite::whereKey($m->visite_id)->value('medecin_id');
                    if (Schema::hasColumn('smis','service_id')) {
                        $m->service_id = Visite::whereKey($m->visite_id)->value('service_id');
                    }
                }
            }
        });
    }

    public function patient()  { return $this->belongsTo(\App\Models\Patient::class); }
    public function visite()   { return $this->belongsTo(\App\Models\Visite::class); }
    // si tu as harmonisé vers personnels :
    public function soignant() { return $this->belongsTo(\App\Models\Personnel::class, 'soignant_id'); }
    public function service()  { return $this->belongsTo(\App\Models\Service::class); } // si colonne ajoutée
}
