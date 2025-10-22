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
        'tarif_id','montant_prevu','montant_du','devise',
    ];

    protected $casts = [
        'heure_arrivee' => 'datetime',
        'clos_at'       => 'datetime',
        'montant_prevu' => 'decimal:2',
        'montant_du'    => 'decimal:2',
    ];

    protected $appends = ['est_soldee'];

    protected static function booted(): void
    {
        // ➕ Remplir le médecin (sans fallback sur l'utilisateur connecté)
        $fillDoctor = function (self $v): void {
            if ($v->medecin_id) {
                $user = \App\Models\User::query()
                    ->with(['personnel:id,user_id,first_name,last_name'])
                    ->find($v->medecin_id);

                if ($user) {
                    $v->medecin_nom = optional($user->personnel)->full_name ?: $user->name;
                }
            } else {
                // si aucun médecin n'est choisi, laisser vide
                $v->medecin_nom = $v->medecin_nom ?: null;
            }
        };

        // Petite fonction interne qu’on réutilise pour "remplir" la tarification
        $fillPricing = function (self $v) {
            $isEmpty = static fn($x) => $x === null || $x === '' || (is_numeric($x) && (float)$x == 0.0);

            // 1) Résoudre le tarif automatiquement si pas fourni
            if (! $v->tarif_id && $v->service_id) {
                // Essayer via la relation "tarif" du service (actif le + récent)
                $service = $v->relationLoaded('service') ? $v->service : $v->service()->first();
                $tarifId = null;

                if ($service && $service->tarif) {
                    $tarifId = $service->tarif->id;
                } else {
                    // Fallback: chercher par service_slug
                    $slug = $service?->slug ?? \App\Models\Service::whereKey($v->service_id)->value('slug');
                    if ($slug) {
                        $tarifId = \App\Models\Tarif::where('service_slug', $slug)
                            ->where('is_active', true)
                            ->orderByDesc('created_at')  // ou ->orderByDesc('id') si pas de timestamps
                            ->value('id');
                    }
                }

                if ($tarifId) {
                    $v->tarif_id = $tarifId;
                }
            }

            // 2) Si on a un tarif et que le montant n'a pas été renseigné ou vaut 0/"" → remplir depuis le tarif
            if ($v->tarif_id && $isEmpty($v->montant_prevu)) {
                // Lire directement en DB pour éviter un 2e chargement du modèle
                $v->montant_prevu = (string) $v->tarif()->value('montant'); // string "1234.00"
                $v->devise        = $v->tarif()->value('devise') ?? 'XAF';
            }

            // 3) Montant dû = prévu si vide/0
            if ($isEmpty($v->montant_du)) {
                $v->montant_du = $v->montant_prevu ?? 0;
            }

            // 4) Defaults divers
            if (! $v->heure_arrivee) $v->heure_arrivee = now();
            $v->statut ??= 'EN_ATTENTE';
        };

        static::creating(function (self $v) use ($fillDoctor, $fillPricing) {
            if (! $v->id) $v->id = (string) Str::uuid();
            $fillDoctor($v);
            $fillPricing($v);
        });

        // Optionnel mais pratique : si on change service/tarif/medecin à l’update, recalcule au besoin
        static::updating(function (self $v) use ($fillDoctor, $fillPricing) {
            if ($v->isDirty('medecin_id') || $v->isDirty('medecin_nom')) {
                $fillDoctor($v);
            }
            if ($v->isDirty(['service_id','tarif_id','montant_prevu','montant_du','devise'])) {
                $fillPricing($v);
            }
        });
    }

    // ----------------
    // Relations
    // ----------------
    public function patient() { return $this->belongsTo(Patient::class); }
    public function service() { return $this->belongsTo(Service::class); } // reste sur service_id
    public function medecin() { return $this->belongsTo(User::class, 'medecin_id'); }
    public function agent()   { return $this->belongsTo(User::class, 'agent_id'); }
    public function tarif()   { return $this->belongsTo(Tarif::class); }
    public function facture() { return $this->hasOne(Facture::class); }

    // ----------------
    // Helpers métier
    // ----------------
    public function envoyerEnCaisse(): void { $this->update(['statut' => 'A_ENCAISSER']); }
    public function marquerPayee(): void    { $this->update(['statut' => 'PAYEE']); }
    public function clore(): void           { $this->update(['statut' => 'CLOTUREE', 'clos_at' => now()]); }

    // ----------------
    // Scopes utiles
    // ----------------
    public function scopeAEncaisser($q) { return $q->where('statut', 'A_ENCAISSER'); }
    public function scopeNonSoldees($q) { return $q->where('montant_du', '>', 0); }

    // ----------------
    // Attribut calculé
    // ----------------
    public function getEstSoldeeAttribute(): bool
    {
        return (float) $this->montant_du <= 0.0;
    }

    // À AJOUTER dans la classe Visite (avec les autres relations)
    public function aru()
    {
        return $this->hasOne(\App\Models\Aru::class, 'visite_id');
    }
}
