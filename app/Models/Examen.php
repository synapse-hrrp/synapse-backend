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
        'prescripteur_externe',
        'reference_demande',

        // traçabilité
        'created_via',          // 'labo' | 'service'
        'created_by_user_id',

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
        // petit helper commun pour remplir depuis la tarification
        $fillFromTarif = function (self $m): void {
            // Normaliser le code si présent
            if ($m->code_examen) {
                $m->code_examen = strtoupper(trim($m->code_examen));
            }

            // On complète si nom/prix manquent
            $needsName = empty($m->nom_examen);
            $needsPrix = !isset($m->prix) || $m->prix === '' || (is_numeric($m->prix) && (float)$m->prix == 0.0);

            if (! $needsName && ! $needsPrix) return;

            $tarifQ = \App\Models\Tarif::query()->actifs();

            // 1) si service fourni, on filtre d'abord par service
            if ($m->service_slug) {
                $tarifQ->forService($m->service_slug);
            }

            // 2) si code fourni, on filtre par code
            if ($m->code_examen) {
                $tarifQ->byCode($m->code_examen);
            }

            // 3) prend le plus récent actif correspondant
            $tarif = $tarifQ->latest('created_at')->first();

            if ($tarif) {
                if ($needsName) {
                    $m->nom_examen = $tarif->libelle ?: $tarif->code;
                }
                if ($needsPrix) {
                    $m->prix   = $tarif->montant;
                }
                // Devise par défaut depuis le tarif si non fournie
                if (!$m->devise) {
                    $m->devise = $tarif->devise ?? 'XAF';
                }
            }
        };

        static::creating(function (self $m) use ($fillFromTarif) {
            if (!$m->id)           $m->id = (string) Str::uuid();
            if (!$m->date_demande) $m->date_demande = now();
            if (!$m->statut)       $m->statut = 'en_attente';

            // Origine explicite selon la présence d'un service
            if (!$m->created_via)  $m->created_via  = $m->service_slug ? 'service' : 'labo';

            // Cohérence avec ancien champ
            if (!$m->type_origine) $m->type_origine = $m->service_slug ? 'interne' : 'externe';

            // Devise par défaut
            if (!$m->devise)       $m->devise = 'XAF';

            // 🔑 Remplir nom/prix/devise depuis Tarifs si besoin
            $fillFromTarif($m);
        });

        // Si on change le code ou le service à l’update, on peut recompléter si prix/nom manquent
        static::updating(function (self $m) use ($fillFromTarif) {
            if ($m->isDirty(['code_examen','service_slug','prix','nom_examen','devise'])) {
                $fillFromTarif($m);
            }
        });
    }

    /** Relations */
    public function patient()     { return $this->belongsTo(Patient::class); }
    public function service()     { return $this->belongsTo(Service::class, 'service_slug', 'slug'); }
    public function demandeur()   { return $this->belongsTo(Medecin::class, 'demande_par'); }
    public function validateur()  { return $this->belongsTo(Personnel::class, 'valide_par'); }
    public function creator()     { return $this->belongsTo(User::class, 'created_by_user_id'); }

    /** Scopes pratiques */
    public function scopeFromService($q) { return $q->where('created_via', 'service'); }
    public function scopeFromLabo($q)    { return $q->where('created_via', 'labo'); }
    public function scopeForService($q, ?string $slug)
    {
        return $slug ? $q->where('service_slug', $slug) : $q->whereNull('service_slug');
    }
}
