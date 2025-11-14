<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Facture extends Model
{
    // --- Identifiant UUID ---
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'factures';

    protected $fillable = [
        'numero',
        'visite_id',
        'patient_id',
        'montant_total',
        'montant_du',
        'devise',
        'statut', // IMPAYEE | PARTIELLE | PAYEE | ANNULEE
    ];

    protected $casts = [
        'montant_total' => 'decimal:2',
        'montant_du'    => 'decimal:2',
        // created_at / updated_at => datetime par défaut
    ];

    // Pour exposer directement le montant payé + le service dans les réponses JSON
    protected $appends = ['montant_paye', 'service_id'];

    protected static function booted(): void
    {
        // UUID & valeurs par défaut
        static::creating(function (self $f) {
            if (! $f->getKey()) $f->id = (string) Str::uuid();
            $f->numero ??= self::nextNumero();
            $f->devise ??= 'CDF';
            $f->statut ??= 'IMPAYEE';
            // Si créé depuis une visite pré-tarifée
            if ($f->montant_total === null && $f->visite?->montant_prevu !== null) {
                $f->montant_total = $f->visite->montant_prevu;
                $f->montant_du    = $f->visite->montant_du ?? $f->montant_total;
                $f->devise        = $f->visite->devise ?? $f->devise;
            }
        });
    }

    /**
     * Génère le prochain numéro de facture (FAC-YYYY-000001).
     * NB: pour trafic élevé, remplace par une table compteur dédiée avec lock.
     */
    public static function nextNumero(): string
    {
        $year = now()->year;
        // ATTENTION: count() n'est pas safe en concurrence élevée.
        // Suffisant pour un setup simple; sinon, table "facture_counters".
        $seq = static::whereYear('created_at', $year)->count() + 1;
        return sprintf('FAC-%d-%06d', $year, $seq);
    }

    // ----------------
    // Relations
    // ----------------
    public function visite(): BelongsTo     { return $this->belongsTo(Visite::class); }
    public function patient(): BelongsTo    { return $this->belongsTo(Patient::class); }
    public function lignes(): HasMany       { return $this->hasMany(FactureLigne::class); }
    public function reglements(): HasMany   { return $this->hasMany(Reglement::class); }

    // ----------------
    // Scopes utiles
    // ----------------
    public function scopeImpayees($q)  { return $q->where('statut', 'IMPAYEE'); }
    public function scopePartielles($q){ return $q->where('statut', 'PARTIELLE'); }
    public function scopePayees($q)    { return $q->where('statut', 'PAYEE'); }
    public function scopeDu($q, $from = null, $to = null) {
        return $q->when($from, fn($qq)=>$qq->whereDate('created_at','>=',$from))
                 ->when($to,   fn($qq)=>$qq->whereDate('created_at','<=',$to));
    }

    // ----------------
    // Attributs calculés
    // ----------------
    public function getMontantPayeAttribute(): string
    {
        // Utilise la relation eager-loaded si dispo, sinon requête.
        $paye = $this->relationLoaded('reglements')
            ? $this->reglements->sum('montant')
            : $this->reglements()->sum('montant');

        return number_format((float) $paye, 2, '.', '');
    }

    /**
     * 🔎 service_id calculé à partir de la visite associée
     * Permet au front d'utiliser directement f.service_id
     */
    public function getServiceIdAttribute(): ?int
    {
        // Si un jour tu ajoutes une vraie colonne service_id, on la respecte
        if (array_key_exists('service_id', $this->attributes ?? [])) {
            return $this->attributes['service_id'] !== null
                ? (int) $this->attributes['service_id']
                : null;
        }

        // Si la visite est déjà chargée
        if ($this->relationLoaded('visite') && $this->visite) {
            return $this->visite->service_id
                ? (int) $this->visite->service_id
                : null;
        }

        // Fallback : on charge la visite si besoin (OK pour usage modéré)
        if ($this->visite) {
            return $this->visite->service_id
                ? (int) $this->visite->service_id
                : null;
        }

        return null;
    }

    public function getEstSoldeeAttribute(): bool
    {
        return (float) $this->montant_du <= 0.0;
    }

    // ----------------
    // Métier
    // ----------------

    /**
     * Recalcule le total, le dû et le statut à partir des lignes & règlements.
     * Idempotent; peut être appelé après toute modification.
     */
    public function recalc(): void
    {
        $total = (float) $this->lignes()->sum('montant');
        $paye  = (float) $this->reglements()->sum('montant');

        $newStatut = $total <= 0
            ? 'IMPAYEE' // facture vide
            : ($paye >= $total ? 'PAYEE' : ($paye > 0 ? 'PARTIELLE' : 'IMPAYEE'));

        $this->forceFill([
            'montant_total' => $total,
            'montant_du'    => max(0, $total - $paye),
            'statut'        => $newStatut,
        ])->saveQuietly();

        // Si soldée => propager sur la visite
        if ($this->wasChanged('statut') && $this->statut === 'PAYEE' && $this->visite) {
            // si tu utilises un enum: $this->visite->update(['statut' => \App\Enums\VisiteStatut::PAYEE]);
            $this->visite->marquerPayee();
        }
    }

    /**
     * Helpers pratiques si tu veux piloter depuis le modèle
     */
    public function ajouterLigne(array $data): FactureLigne
    {
        // $data = ['designation','quantite','prix_unitaire','tarif_id' (opt)]
        $ligne = $this->lignes()->create([
            'designation'   => $data['designation'],
            'quantite'      => $data['quantite'],
            'prix_unitaire' => $data['prix_unitaire'],
            'tarif_id'      => $data['tarif_id'] ?? null,
            'montant'       => (float)$data['quantite'] * (float)$data['prix_unitaire'],
        ]);

        $this->recalc();
        return $ligne;
    }

    public function enregistrerReglement(array $data): Reglement
    {
        // $data = ['montant','mode','reference' (opt),'devise'(opt)]
        $reg = $this->reglements()->create([
            'montant'   => $data['montant'],
            'mode'      => $data['mode'],
            'reference' => $data['reference'] ?? null,
            'devise'    => $data['devise'] ?? $this->devise,
        ]);

        $this->recalc();
        return $reg;
    }

    public function annuler(): void
    {
        $this->update(['statut' => 'ANNULEE']);
    }
}
