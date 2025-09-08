<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BlocOperatoire extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'bloc_operatoires';

    protected $fillable = [
        'patient_id','visite_id',
        'soignant_id','chirurgien_id','anesthesiste_id','infirmier_bloc_id',
        'date_intervention','type_intervention','cote','classification_asa','type_anesthesie',
        'heure_entree_bloc','heure_debut','heure_fin','heure_sortie_bloc','duree_minutes',
        'indication','gestes_realises','compte_rendu','incident_accident','pertes_sanguines','antibioprophylaxie',
        'destination_postop','consignes_postop','statut',
    ];

    protected $casts = [
        'date_intervention' => 'datetime',
        'duree_minutes'     => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!$m->id)               $m->id = (string) Str::uuid();
            if (!$m->statut)           $m->statut = 'planifie';
            if ($m->heure_debut && $m->heure_fin && !$m->duree_minutes) {
                $m->duree_minutes = self::calcDuration($m->heure_debut, $m->heure_fin);
            }
        });

        static::saving(function (self $m) {
            if ($m->heure_debut && $m->heure_fin) {
                $m->duree_minutes = self::calcDuration($m->heure_debut, $m->heure_fin);
            }
        });
    }

    public static function calcDuration(?string $debut, ?string $fin): ?int
    {
        if (!$debut || !$fin) return null;
        try {
            $d = \Carbon\Carbon::createFromFormat('H:i:s', strlen($debut) === 5 ? $debut.':00' : $debut);
            $f = \Carbon\Carbon::createFromFormat('H:i:s', strlen($fin)   === 5 ? $fin.':00'   : $fin);
            return max(0, $d->diffInMinutes($f));
        } catch (\Throwable $e) {
            return null;
        }
    }

    // Relations
    public function patient()        { return $this->belongsTo(\App\Models\Patient::class); }
    public function visite()         { return $this->belongsTo(\App\Models\Visite::class); }
    public function soignant()       { return $this->belongsTo(\App\Models\User::class, 'soignant_id'); }
    public function chirurgien()     { return $this->belongsTo(\App\Models\User::class, 'chirurgien_id'); }
    public function anesthesiste()   { return $this->belongsTo(\App\Models\User::class, 'anesthesiste_id'); }
    public function infirmierBloc()  { return $this->belongsTo(\App\Models\User::class, 'infirmier_bloc_id'); }
}
