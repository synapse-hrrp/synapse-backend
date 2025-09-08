<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class GestionMalade extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'gestion_malades';

    protected $fillable = [
        'patient_id','visite_id','soignant_id',
        'date_acte',
        'type_action',
        'service_source','service_destination',
        'pavillon','chambre','lit',
        'date_entree','date_sortie_prevue','date_sortie_effective',
        'motif','diagnostic','examen_clinique','traitements','observation',
        'statut',
    ];

    protected $casts = [
        'date_acte'             => 'datetime',
        'date_entree'           => 'datetime',
        'date_sortie_prevue'    => 'datetime',
        'date_sortie_effective' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!$m->id)        $m->id = (string) Str::uuid();
            if (!$m->date_acte) $m->date_acte = now();
            if (!$m->statut)    $m->statut = 'en_cours';
        });
    }

    public function patient()  { return $this->belongsTo(\App\Models\Patient::class); }
    public function visite()   { return $this->belongsTo(\App\Models\Visite::class); }
    public function soignant() { return $this->belongsTo(\App\Models\User::class, 'soignant_id'); }
}
