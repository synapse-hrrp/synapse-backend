<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Echographie extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'echographies';

    protected $fillable = [
        'patient_id',
        'service_slug',         // <- remplace service_id
        'type_origine',         // interne | externe
        'prescripteur_externe',
        'reference_demande',

        // traçabilité
        'created_via',          // 'labo' | 'service'
        'created_by_user_id',

        // infos écho
        'code_echo',            // code tarifaire/acte
        'nom_echo',             // libellé affiché
        'indication',           // raison clinique / indication
        'statut',               // en_attente | en_cours | termine | valide
        'compte_rendu',         // texte libre du CR
        'conclusion',           // synthèse/avis
        'mesures_json',         // mesures structurées (ex: diamètres, biométrie…)
        'images_json',          // chemins/urls d’images associées

        // facturation
        'prix','devise','facture_id',

        // workflow
        'demande_par','date_demande',
        'realise_par','date_realisation',
        'valide_par','date_validation',
    ];

    protected $casts = [
        'date_demande'     => 'datetime',
        'date_realisation' => 'datetime',
        'date_validation'  => 'datetime',
        'mesures_json'     => 'array',
        'images_json'      => 'array',
        'prix'             => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // helper commun : compléter nom/prix/devise depuis la tarification
        $fillFromTarif = function (self $m): void {
            if ($m->code_echo) {
                $m->code_echo = strtoupper(trim($m->code_echo));
            }

            $needsName = empty($m->nom_echo);
            $needsPrix = !isset($m->prix) || $m->prix === '' || (is_numeric($m->prix) && (float) $m->prix == 0.0);

            if (! $needsName && ! $needsPrix) return;

            $tarifQ = \App\Models\Tarif::query()->actifs();

            // 1) si service fourni, on filtre d'abord par service
            if ($m->service_slug) {
                $tarifQ->forService($m->service_slug);
            }

            // 2) si code fourni, on filtre par code
            if ($m->code_echo) {
                $tarifQ->byCode($m->code_echo);
            }

            // 3) prend le plus récent actif correspondant
            $tarif = $tarifQ->latest('created_at')->first();

            if ($tarif) {
                if ($needsName) {
                    $m->nom_echo = $tarif->libelle ?: $tarif->code;
                }
                if ($needsPrix) {
                    $m->prix = $tarif->montant;
                }
                if (! $m->devise) {
                    $m->devise = $tarif->devise ?? 'XAF';
                }
            }
        };

        static::creating(function (self $m) use ($fillFromTarif) {
            if (! $m->id)             $m->id = (string) Str::uuid();
            if (! $m->date_demande)   $m->date_demande = now();
            if (! $m->statut)         $m->statut = 'en_attente';

            // Origine explicite selon la présence d'un service
            if (! $m->created_via)    $m->created_via  = $m->service_slug ? 'service' : 'labo';

            // Cohérence avec ancien champ
            if (! $m->type_origine)   $m->type_origine = $m->service_slug ? 'interne' : 'externe';

            // Devise par défaut
            if (! $m->devise)         $m->devise = 'XAF';

            // compléter depuis Tarifs si besoin
            $fillFromTarif($m);
        });

        // Recompléter si code/service/prix/nom/devise changent
        static::updating(function (self $m) use ($fillFromTarif) {
            if ($m->isDirty(['code_echo','service_slug','prix','nom_echo','devise'])) {
                $fillFromTarif($m);
            }
        });
    }

    /** Relations */
    public function patient()     { return $this->belongsTo(Patient::class); }
    public function service()     { return $this->belongsTo(Service::class, 'service_slug', 'slug'); }
    public function demandeur()   { return $this->belongsTo(Personnel::class, 'demande_par'); }
    public function operateur()   { return $this->belongsTo(Personnel::class, 'realise_par'); }   // sonographe/radiologue
    public function validateur()  { return $this->belongsTo(Personnel::class, 'valide_par'); }
    public function creator()     { return $this->belongsTo(User::class, 'created_by_user_id'); }

    /** Scopes pratiques (mêmes patterns que Examen) */
    public function scopeFromService($q) { return $q->where('created_via', 'service'); }
    public function scopeFromLabo($q)    { return $q->where('created_via', 'labo'); }
    public function scopeForService($q, ?string $slug)
    {
        return $slug ? $q->where('service_slug', $slug) : $q->whereNull('service_slug');
    }
}
