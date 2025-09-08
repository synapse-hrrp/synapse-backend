<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Aru extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'arus';

    protected $fillable = [
        'patient_id','visite_id','soignant_id',
        'date_acte','motif','triage_niveau',
        'tension_arterielle','temperature','frequence_cardiaque','frequence_respiratoire',
        'saturation','douleur_echelle','glasgow',
        'examens_complementaires','traitements','observation',
        'statut',
    ];

    protected $casts = [
        'date_acte'             => 'datetime',
        'temperature'           => 'decimal:1',
        'frequence_cardiaque'   => 'integer',
        'frequence_respiratoire'=> 'integer',
        'saturation'            => 'integer',
        'douleur_echelle'       => 'integer',
        'glasgow'               => 'integer',
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
