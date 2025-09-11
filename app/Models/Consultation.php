<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Consultation extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'consultations';

    protected $fillable = [
        'patient_id','visite_id','soignant_id',
        'medecin_id',       // médecin responsable
        'date_acte',
        'categorie','type_consultation',
        'motif','examen_clinique','diagnostic','prescriptions','orientation_service',
        'donnees_specifiques',
        'statut',
    ];

    protected $casts = [
        'date_acte' => 'datetime',
        'donnees_specifiques' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!$m->id)        $m->id = (string) Str::uuid();
            if (!$m->date_acte) $m->date_acte = now();
            if (!$m->statut)    $m->statut = 'en_cours';
        });
    }

    public function patient()  { return $this->belongsTo(Patient::class); }
    public function visite()   { return $this->belongsTo(Visite::class); }
    public function soignant() { return $this->belongsTo(User::class, 'soignant_id'); }
    public function medecin()  { return $this->belongsTo(\App\Models\User::class, 'medecin_id'); } //
}


