<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BilletSortie extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'billets_sortie';

    protected $fillable = [
        'patient_id',
        'service_slug',          // service d'hospitalisation/suivi (nullable)
        'admission_id',          // si module d'admission existe (nullable)

        // traçabilité
        'created_via',           // 'service' | 'med' | 'admin'
        'created_by_user_id',

        // contenu clinique
        'motif_sortie',          // guérison | sortie demandée | transfert | décès | autre
        'diagnostic_sortie',
        'resume_clinique',
        'consignes',
        'traitement_sortie_json',// ordonnances / posologies (array)
        'rdv_controle_at',       // prochain RDV
        'destination',           // domicile | autre établissement ...

        // métadonnées
        'statut',                // brouillon | valide | remis
        'remis_a',               // nom de la personne à qui remis (si différent du patient)
        'signature_par',         // personnel qui signe
        'date_signature',
        'date_sortie_effective',
    ];

    protected $casts = [
        'traitement_sortie_json' => 'array',
        'rdv_controle_at'        => 'datetime',
        'date_signature'         => 'datetime',
        'date_sortie_effective'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (! $m->id)          $m->id = (string) Str::uuid();
            if (! $m->statut)      $m->statut = 'brouillon';
            if (! $m->created_via) $m->created_via = $m->service_slug ? 'service' : 'med';
        });
    }

    /** Relations */
    public function patient()    { return $this->belongsTo(Patient::class); }
    public function service()    { return $this->belongsTo(Service::class, 'service_slug', 'slug'); }
    //public function admission()  { return $this->belongsTo(Admission::class, 'admission_id'); } // si tu as ce modèle
    public function signataire() { return $this->belongsTo(Personnel::class, 'signature_par'); }
    public function creator()    { return $this->belongsTo(User::class, 'created_by_user_id'); }

    /** Scopes pratiques */
    public function scopeFromService($q) { return $q->where('created_via', 'service'); }
    public function scopeForService($q, ?string $slug)
    {
        return $slug ? $q->where('service_slug', $slug) : $q->whereNull('service_slug');
    }
}
