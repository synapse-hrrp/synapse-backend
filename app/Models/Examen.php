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
        /**
         * Remplissage depuis la tarification
         * @param self   $m
         * @param bool   $force  Si true : remplace TOUJOURS nom/prix/devise par le tarif trouvé
         */
        $fillFromTarif = function (self $m, bool $force = false): void {
            // Normaliser le code si présent
            if ($m->code_examen) {
                $m->code_examen = strtoupper(trim($m->code_examen));
            }

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

            if (! $tarif) return;

            if ($force) {
                // On ECRASE pour refléter le nouveau tarif
                $m->nom_examen = $tarif->libelle ?: $tarif->code;
                $m->prix       = $tarif->montant;
                $m->devise     = $tarif->devise ?: ($m->devise ?: 'XAF');
            } else {
                // On complète uniquement si manquants
                if (empty($m->nom_examen)) {
                    $m->nom_examen = $tarif->libelle ?: $tarif->code;
                }
                $needsPrix = !isset($m->prix) || $m->prix === '' || (is_numeric($m->prix) && (float)$m->prix == 0.0);
                if ($needsPrix) {
                    $m->prix = $tarif->montant;
                }
                if (!$m->devise) {
                    $m->devise = $tarif->devise ?? 'XAF';
                }
            }
        };

        // --- CREATING ---
        static::creating(function (self $m) use ($fillFromTarif) {
            if (!$m->id)            $m->id = (string) Str::uuid();

            // Date de demande TOUJOURS auto
            $m->date_demande = now();

            if (!$m->statut)        $m->statut = 'en_attente';

            // Origine explicite selon la présence d'un service
            $m->created_via  = $m->service_slug ? 'service' : 'labo';
            $m->type_origine = $m->service_slug ? 'interne' : 'externe';

            // Interne -> pas de prescripteur externe
            if ($m->type_origine === 'interne') {
                $m->prescripteur_externe = null;
            }

            // Devise par défaut
            if (!$m->devise) $m->devise = 'XAF';

            // Remplir nom/prix/devise depuis Tarifs si besoin
            $fillFromTarif($m, false);

            // Date de validation auto selon statut
            if ($m->statut === 'valide') {
                $m->date_validation = now();
            } else {
                $m->date_validation = null;
            }
        });

        // --- UPDATING ---
        static::updating(function (self $m) use ($fillFromTarif) {

            // Si on change le service ou le code examen -> FORCER la synchro tarif (remplacer prix/devise/nom)
            if ($m->isDirty(['service_slug']) || $m->isDirty(['code_examen'])) {
                $fillFromTarif($m, true);
            } else {
                // Sinon, compléter au besoin (si manquants)
                if ($m->isDirty(['prix','nom_examen','devise'])) {
                    $fillFromTarif($m, false);
                }
            }

            // Recalcule origine et created_via selon service
            $m->created_via  = $m->service_slug ? 'service' : 'labo';
            $m->type_origine = $m->service_slug ? 'interne' : 'externe';

            // Interne -> nettoyer prescripteur_externe
            if ($m->type_origine === 'interne') {
                $m->prescripteur_externe = null;
            }

            // Dates auto selon statut
            if ($m->isDirty('statut')) {
                if ($m->statut === 'valide') {
                    $m->date_validation = now();
                } else {
                    $m->date_validation = null;
                }
            }
        });
    }

    /** --------- Relations --------- */

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_slug', 'slug');
    }

    // Demandeur = médecin (clé étrangère sur medecins.id)
    public function demandeur()
    {
        return $this->belongsTo(Medecin::class, 'demande_par');
    }

    // Validateur = personnel
    public function validateur()
    {
        return $this->belongsTo(Personnel::class, 'valide_par');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Facture
    public function facture()
    {
        return $this->belongsTo(Facture::class, 'facture_id');
    }

    /** --------- Scopes pratiques --------- */
    public function scopeFromService($q) { return $q->where('created_via', 'service'); }
    public function scopeFromLabo($q)    { return $q->where('created_via', 'labo'); }
    public function scopeForService($q, ?string $slug)
    {
        return $slug ? $q->where('service_slug', $slug) : $q->whereNull('service_slug');
    }
}
