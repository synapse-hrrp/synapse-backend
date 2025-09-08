<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Visite extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'visites';

    protected $fillable = [
        'patient_id','service_id',
        'medecin_id','medecin_nom',
        'agent_id','agent_nom',
        'heure_arrivee',
        'plaintes_motif','hypothese_diagnostic',
        'affectation_id','statut','clos_at',

        // Pricing minimal (aligné à la migration)
        'tarif_id','montant_prevu','montant_du','devise',
    ];

    protected $casts = [
        'heure_arrivee'   => 'datetime',
        'clos_at'         => 'datetime',
        'montant_prevu'   => 'decimal:2',
        'montant_du'      => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $v) {
            if (!$v->id) $v->id = (string) Str::uuid();
            if (!$v->heure_arrivee) $v->heure_arrivee = now();
        });
    }

    // Relations
    public function patient() { return $this->belongsTo(Patient::class); }
    public function service() { return $this->belongsTo(Service::class); }
    public function medecin() { return $this->belongsTo(User::class, 'medecin_id'); }
    public function agent()   { return $this->belongsTo(User::class, 'agent_id'); }
    public function tarif()   { return $this->belongsTo(Tarif::class); }

    // public function affectation() { return $this->belongsTo(Affectation::class); }
}
