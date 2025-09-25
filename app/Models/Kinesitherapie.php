<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Visite;

class Kinesitherapie extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'kinesitherapies';

    // soignant_id calculé → pas dans fillable
    protected $fillable = [
        'patient_id','visite_id','service_id',
        'date_acte',
        'motif','diagnostic','evaluation','objectifs','techniques',
        'zone_traitee','intensite_douleur','echelle_borg',
        'nombre_seances','duree_minutes',
        'resultats','conseils',
        'statut',
    ];

    protected $casts = [
        'date_acte'         => 'datetime',
        'intensite_douleur' => 'integer',
        'echelle_borg'      => 'integer',
        'nombre_seances'    => 'integer',
        'duree_minutes'     => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (! $m->id)        $m->id = (string) Str::uuid();
            if (! $m->date_acte) $m->date_acte = now();
            if (! $m->statut)    $m->statut = 'planifie';

            // verrouiller soignant/patient/service depuis la visite
            if ($m->visite_id) {
                $v = Visite::find($m->visite_id);
                if ($v) {
                    if (empty($m->soignant_id)) $m->soignant_id = $v->medecin_id;
                    if (empty($m->patient_id))  $m->patient_id  = $v->patient_id;
                    if (Schema::hasColumn('kinesitherapies', 'service_id') && empty($m->service_id)) {
                        $m->service_id = $v->service_id;
                    }
                }
            }
        });

        static::updating(function (self $m) {
            // si la visite change, resynchroniser les champs dépendants
            if ($m->isDirty('visite_id') && $m->visite_id) {
                $v = Visite::find($m->visite_id);
                if ($v) {
                    $m->soignant_id = $v->medecin_id;
                    if (empty($m->patient_id)) $m->patient_id = $v->patient_id;
                    if (Schema::hasColumn('kinesitherapies', 'service_id')) {
                        $m->service_id = $v->service_id;
                    }
                }
            }
        });
    }

    public function patient()  { return $this->belongsTo(\App\Models\Patient::class); }
    public function visite()   { return $this->belongsTo(\App\Models\Visite::class); }
    public function soignant() { return $this->belongsTo(\App\Models\Personnel::class, 'soignant_id'); }
    public function service()  { return $this->belongsTo(\App\Models\Service::class); }
}
