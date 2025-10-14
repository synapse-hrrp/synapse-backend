<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Hospitalisation extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'hospitalisations';

    protected $fillable = [
        'patient_id',
        'service_slug',              // ex: medecine, chirurgie…
        'admission_no',              // numéro interne admission/séjour (optionnel)
        'created_via',               // 'service' | 'med' | 'admin'
        'created_by_user_id',

        // logistique
        'unite',                     // unité/pavillon
        'chambre',                   // chambre (texte libre)
        'lit',                       // lit (texte libre)
        'lit_id',                    // si tu gères une table lits
        'medecin_traitant_id',       // personnels.id

        // données cliniques
        'motif_admission',
        'diagnostic_entree',
        'diagnostic_sortie',
        'notes',
        'prise_en_charge_json',      // json (surveillance, perfusions, diet, etc.)

        // workflow & datations
        'statut',                    // en_cours | transfere | sorti | annule
        'date_admission',
        'date_sortie_prevue',
        'date_sortie_reelle',

        // facturation (optionnel)
        'facture_id',
    ];

    protected $casts = [
        'date_admission'       => 'datetime',
        'date_sortie_prevue'   => 'datetime',
        'date_sortie_reelle'   => 'datetime',
        'prise_en_charge_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (! $m->id)          $m->id = (string) Str::uuid();
            if (! $m->statut)      $m->statut = 'en_cours';
            if (! $m->date_admission) $m->date_admission = now();
            if (! $m->created_via) $m->created_via = $m->service_slug ? 'service' : 'med';
        });
    }

    /** Relations */
    public function patient()           { return $this->belongsTo(Patient::class); }
    public function service()           { return $this->belongsTo(Service::class, 'service_slug', 'slug'); }
    public function medecinTraitant()   { return $this->belongsTo(Personnel::class, 'medecin_traitant_id'); }
    public function creator()           { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function facture()           { return $this->belongsTo(Facture::class, 'facture_id'); }

    /** Scopes pratiques */
    public function scopeFromService($q) { return $q->where('created_via', 'service'); }
    public function scopeForService($q, ?string $slug)
    {
        return $slug ? $q->where('service_slug', $slug) : $q->whereNull('service_slug');
    }
}
