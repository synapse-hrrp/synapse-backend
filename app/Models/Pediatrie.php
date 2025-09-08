<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Pediatrie extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'pediatries';

    protected $fillable = [
        'id','patient_id','visite_id','soignant_id',
        'date_acte','motif','diagnostic',
        'poids','taille','temperature','perimetre_cranien',
        'saturation','frequence_cardiaque','frequence_respiratoire',
        'examen_clinique','traitements','observation',
        'statut',
    ];

    protected $casts = [
        'date_acte'              => 'datetime',
        'poids'                  => 'decimal:2',
        'taille'                 => 'decimal:2',
        'temperature'            => 'decimal:1',
        'perimetre_cranien'      => 'decimal:1',
        'saturation'             => 'integer',
        'frequence_cardiaque'    => 'integer',
        'frequence_respiratoire' => 'integer',
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
