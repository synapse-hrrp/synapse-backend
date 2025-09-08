<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Kinesitherapie extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'kinesitherapies';

    protected $fillable = [
        'patient_id','visite_id','soignant_id',
        'date_acte',
        'motif','diagnostic','evaluation','objectifs','techniques',
        'zone_traitee','intensite_douleur','echelle_borg',
        'nombre_seances','duree_minutes',
        'resultats','conseils',
        'statut',
    ];

    protected $casts = [
        'date_acte'        => 'datetime',
        'intensite_douleur'=> 'integer',
        'echelle_borg'     => 'integer',
        'nombre_seances'   => 'integer',
        'duree_minutes'    => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!$m->id)        $m->id = (string) Str::uuid();
            if (!$m->date_acte) $m->date_acte = now();
            if (!$m->statut)    $m->statut = 'planifie';
        });
    }

    public function patient()  { return $this->belongsTo(\App\Models\Patient::class); }
    public function visite()   { return $this->belongsTo(\App\Models\Visite::class); }
    public function soignant() { return $this->belongsTo(\App\Models\User::class, 'soignant_id'); }
}
