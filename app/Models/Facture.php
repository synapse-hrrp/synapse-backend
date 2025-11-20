<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Facture extends Model
{
    // --- Identifiant UUID ---
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $table = 'factures';

    protected $fillable = [
        'numero',
        'visite_id',
        'patient_id',
        'service_id',      // 👈 colonne réelle dans la table factures
        'montant_total',
        'montant_du',
        'devise',
        'statut', // IMPAYEE | PARTIELLE | PAYEE | ANNULEE
    ];

    protected $casts = [
        'montant_total' => 'decimal:2',
        'montant_du'    => 'decimal:2',
    ];

    // On expose directement le montant payé + service_id dans le JSON
    protected $appends = ['montant_paye', 'service_id'];

    protected static function booted(): void
    {
        // UUID & valeurs par défaut
        static::creating(function (self $f) {
            if (! $f->getKey()) {
                $f->id = (string) Str::uuid();
            }
            $f->numero ??= self::nextNumero();
            $f->devise ??= 'CDF';
            $f->statut ??= 'IMPAYEE';

            // Si créé depuis une visite pré-tarifée
            if ($f->montant_total === null && $f->visite?->montant_prevu !== null) {
                $f->montant_total = $f->visite->montant_prevu;
                $f->montant_du    = $f->visite->montant_du ?? $f->montant_total;
                $f->devise        = $f->visite->devise ?? $f->devise;

                // si la visite a un service_id, on le copie
                if (! $f->service_id && $f->visite->service_id) {
                    $f->service_id = (int) $f->visite->service_id;
                }
            }
        });
    }

    /**
     * Génère le prochain numéro de facture (FAC-YYYY-000001).
     */
    public static function nextNumero(): string
    {
        $year = now()->year;
        $seq  = static::whereYear('created_at', $year)->count() + 1;
        return sprintf('FAC-%d-%06d', $year, $seq);
    }

    // ----------------
    // Relations
    // ----------------
    public function visite(): BelongsTo
    {
        return $this->belongsTo(Visite::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(FactureLigne::class);
    }

    public function reglements(): HasMany
    {
        return $this->hasMany(Reglement::class);
    }

    /**
     * Tous les examens rattachés à cette facture
     * (FK = facture_id sur la table examens)
     */
    public function examens(): HasMany
    {
        return $this->hasMany(Examen::class, 'facture_id', 'id');
    }

    /**
     * Lien direct vers le service, si factures.service_id est renseigné.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    // ----------------
    // Scopes utiles
    // ----------------
    public function scopeImpayees($q)   { return $q->where('statut', 'IMPAYEE'); }
    public function scopePartielles($q) { return $q->where('statut', 'PARTIELLE'); }
    public function scopePayees($q)     { return $q->where('statut', 'PAYEE'); }

    public function scopeDu($q, $from = null, $to = null)
    {
        return $q->when($from, fn($qq)=>$qq->whereDate('created_at','>=',$from))
                 ->when($to,   fn($qq)=>$qq->whereDate('created_at','<=',$to));
    }

    // ----------------
    // Attributs calculés
    // ----------------
    public function getMontantPayeAttribute(): string
    {
        $paye = $this->relationLoaded('reglements')
            ? $this->reglements->sum('montant')
            : $this->reglements()->sum('montant');

        return number_format((float) $paye, 2, '.', '');
    }

    /**
     * 🔎 service_id calculé, priorité :
     *  1) colonne réelle factures.service_id
     *  2) visite.service_id
     *  3) examens.service_slug -> services.slug -> id
     */
    public function getServiceIdAttribute(): ?int
    {
        // 1) colonne réelle sur la table factures
        if (array_key_exists('service_id', $this->attributes ?? [])) {
            if (! is_null($this->attributes['service_id'])) {
                return (int) $this->attributes['service_id'];
            }
        }

        // 2) via la visite si présente
        if ($this->relationLoaded('visite') && $this->visite) {
            if (! empty($this->visite->service_id)) {
                return (int) $this->visite->service_id;
            }
        } elseif (! empty($this->visite)) {
            if (! empty($this->visite->service_id)) {
                return (int) $this->visite->service_id;
            }
        }

        // 3) via les examens rattachés (labo, imagerie, etc.)
        $exam = $this->relationLoaded('examens')
            ? $this->examens->first()
            : $this->examens()->first();

        if ($exam && ! empty($exam->service_slug)) {
            $serviceId = Service::where('slug', $exam->service_slug)->value('id');
            return $serviceId ? (int) $serviceId : null;
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
     */
    public function recalc(): void
    {
        $total = (float) $this->lignes()->sum('montant');
        $paye  = (float) $this->reglements()->sum('montant');

        $newStatut = $total <= 0
            ? 'IMPAYEE'
            : ($paye >= $total ? 'PAYEE' : ($paye > 0 ? 'PARTIELLE' : 'IMPAYEE'));

        $this->forceFill([
            'montant_total' => $total,
            'montant_du'    => max(0, $total - $paye),
            'statut'        => $newStatut,
        ])->saveQuietly();

        // Si soldée => propager sur la visite
        if ($this->wasChanged('statut') && $this->statut === 'PAYEE' && $this->visite) {
            $this->visite->marquerPayee();
        }
    }

    public function ajouterLigne(array $data): FactureLigne
    {
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
