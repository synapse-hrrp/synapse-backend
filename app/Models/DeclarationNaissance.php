<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DeclarationNaissance extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'declarations_naissance';

    protected $fillable = [
        'patient_id',              // nouveau-né (Patient)
        'mere_id',                 // mère (Patient)
        'pere_id',                 // père (Patient) - optionnel
        'service_slug',            // ex: maternité
        'accouchement_id',         // si tu as un module Accouchement (optionnel)

        // traçabilité
        'created_via',             // 'service' | 'med' | 'admin'
        'created_by_user_id',

        // données de naissance
        'date_heure_naissance',
        'lieu_naissance',
        'sexe',                    // M | F | I (indéterminé)
        'poids_kg',
        'taille_cm',
        'apgar_1',
        'apgar_5',

        // état-civil
        'numero_acte',             // n° acte état-civil si déjà attribué
        'officier_etat_civil',
        'documents_json',          // pièces jointes: actes, photos, scans…

        // workflow
        'statut',                  // brouillon | valide | transmis
        'date_transmission',       // si transmis à l'état-civil
    ];

    protected $casts = [
        'date_heure_naissance' => 'datetime',
        'documents_json'       => 'array',
        'date_transmission'    => 'datetime',
        'poids_kg'             => 'decimal:2',
        'taille_cm'            => 'decimal:2',
        'apgar_1'              => 'integer',
        'apgar_5'              => 'integer',
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
    public function patient()   { return $this->belongsTo(Patient::class); }                 // bébé
    public function mere()      { return $this->belongsTo(Patient::class, 'mere_id'); }
    public function pere()      { return $this->belongsTo(Patient::class, 'pere_id'); }
    public function service()   { return $this->belongsTo(Service::class, 'service_slug', 'slug'); }
    //public function accouchement() { return $this->belongsTo(Accouchement::class, 'accouchement_id'); } // si existant
    public function creator()   { return $this->belongsTo(User::class, 'created_by_user_id'); }

    /** Scopes pratiques */
    public function scopeFromService($q) { return $q->where('created_via', 'service'); }
    public function scopeForService($q, ?string $slug)
    {
        return $slug ? $q->where('service_slug', $slug) : $q->whereNull('service_slug');
    }
}
