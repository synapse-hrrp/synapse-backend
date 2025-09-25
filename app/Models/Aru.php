<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\Visite;

class Aru extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'arus';

    // ⚠️ on RETIRE 'soignant_id' de $fillable pour qu'il ne puisse JAMAIS être mass-assigné
    protected $fillable = [
        'patient_id','visite_id','service_id',
        'date_acte','motif','triage_niveau',
        'tension_arterielle','temperature','frequence_cardiaque','frequence_respiratoire',
        'saturation','douleur_echelle','glasgow',
        'examens_complementaires','traitements','observation',
        'statut',
    ];

    protected $casts = [
        'date_acte'               => 'datetime',
        'temperature'             => 'decimal:1',
        'frequence_cardiaque'     => 'integer',
        'frequence_respiratoire'  => 'integer',
        'saturation'              => 'integer',
        'douleur_echelle'         => 'integer',
        'glasgow'                 => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (! $m->id)        $m->id = (string) Str::uuid();
            if (! $m->date_acte) $m->date_acte = now();
            if (! $m->statut)    $m->statut = 'en_cours';

            // 🔒 Source de vérité : médecin de la visite
            if ($m->visite_id && empty($m->soignant_id)) {
                $m->soignant_id = Visite::whereKey($m->visite_id)->value('medecin_id');
            }
        });

        static::updating(function (self $m) {
            // Si la visite change OU si soignant_id est vide, on resynchronise
            if ($m->isDirty('visite_id') || empty($m->soignant_id)) {
                if ($m->visite_id) {
                    $m->soignant_id = Visite::whereKey($m->visite_id)->value('medecin_id');
                }
            }
        });
    }

    public function patient()  { return $this->belongsTo(\App\Models\Patient::class); }
    public function visite()   { return $this->belongsTo(\App\Models\Visite::class); }
    public function service()  { return $this->belongsTo(\App\Models\Service::class); }
    public function soignant() { return $this->belongsTo(\App\Models\User::class, 'soignant_id'); }
}
