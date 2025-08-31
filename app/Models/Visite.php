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
        'patient_id','service_id',             // <-- remplace service_code par service_id
        'medecin_id','medecin_nom',
        'agent_id','agent_nom',
        'heure_arrivee',
        'plaintes_motif','hypothese_diagnostic',
        'affectation_id','statut','clos_at',
        // pricing (si colonnes ajoutées)
        //'tarif_id','montant_prevu','remise_pct','montant_du','devise','statut_paiement','motif_gratuite','facture_id',

        // (option) snapshot du code si tu as ajouté une colonne service_code SANS FK
        // 'service_code',
    ];

    protected $casts = [
        'heure_arrivee' => 'datetime',
        'clos_at'       => 'datetime',
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
    public function service() { return $this->belongsTo(Service::class); }  // <-- plus besoin de clés custom
    public function medecin() { return $this->belongsTo(User::class, 'medecin_id'); }
    public function agent()   { return $this->belongsTo(User::class, 'agent_id'); }

    // public function affectation() { return $this->belongsTo(Affectation::class); }
    // public function tarif()       { return $this->belongsTo(Tarif::class); }
}
