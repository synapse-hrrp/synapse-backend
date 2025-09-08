<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Sanitaire extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'sanitaires';

    protected $fillable = [
        'patient_id','visite_id','soignant_id',
        'date_acte','date_debut','date_fin',
        'type_action','zone','sous_zone','niveau_risque',
        'produits_utilises','equipe','duree_minutes','cout',
        'observation','statut',
    ];

    protected $casts = [
        'date_acte'     => 'datetime',
        'date_debut'    => 'datetime',
        'date_fin'      => 'datetime',
        'equipe'        => 'array',
        'cout'          => 'decimal:2',
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
