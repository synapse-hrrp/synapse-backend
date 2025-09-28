<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Examen extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'examens';

    protected $fillable = [
        'patient_id',
        'service_slug',         // <- remplace service_id
        'type_origine',         // interne | externe
        'prescripteur_externe', // nullable
        'reference_demande',    // nullable

        // --- traçabilité de création
        'created_via',          // 'labo' | 'service'
        'created_by_user_id',   // utilisateur qui a créé

        'code_examen','nom_examen','prelevement',
        'statut', // en_attente | en_cours | termine | valide
        'valeur_resultat','unite','intervalle_reference','resultat_json',
        'prix','devise','facture_id',
        'demande_par','date_demande',
        'valide_par','date_validation',
    ];

    protected $casts = [
        'date_demande'    => 'datetime',
        'date_validation' => 'datetime',
        'resultat_json'   => 'array',
        'prix'            => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!$m->id)            $m->id = (string) Str::uuid();
            if (!$m->date_demande)  $m->date_demande = now();
            if (!$m->statut)        $m->statut = 'en_attente';

            // Origine explicite selon la présence d'un service
            if (!$m->created_via)  $m->created_via  = $m->service_slug ? 'service' : 'labo';

            // Cohérence avec l'ancien champ d'origine
            if (!$m->type_origine) $m->type_origine = $m->service_slug ? 'interne' : 'externe';

            // Devise par défaut
            if (!$m->devise)       $m->devise = 'XAF';
        });
    }

    /** Relations */
    public function patient()     { return $this->belongsTo(Patient::class); }

    // IMPORTANT : foreignKey = service_slug, ownerKey = slug
    public function service()     { return $this->belongsTo(Service::class, 'service_slug', 'slug'); }

    public function demandeur()   { return $this->belongsTo(Personnel::class, 'demande_par'); }
    public function validateur()  { return $this->belongsTo(Personnel::class, 'valide_par'); }

    // (Optionnel) si tu as un modèle User
    public function creator()     { return $this->belongsTo(User::class, 'created_by_user_id'); }

    /** Scopes pratiques */
    public function scopeFromService($q) { return $q->where('created_via', 'service'); }
    public function scopeFromLabo($q)    { return $q->where('created_via', 'labo'); }
    public function scopeForService($q, ?string $slug)
    {
        return $slug ? $q->where('service_slug', $slug) : $q->whereNull('service_slug');
    }
}
