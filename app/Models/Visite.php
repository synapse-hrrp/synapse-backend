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

        // Pricing minimal (aligné à la migration)
        'tarif_id','montant_prevu','montant_du','devise',
    ];

    protected $casts = [
        'heure_arrivee' => 'datetime',
        'clos_at'       => 'datetime',
        'montant_prevu' => 'decimal:2',
        'montant_du'    => 'decimal:2',
        // si tu as un enum PHP 8.1, tu peux caster ici: 'statut' => \App\Enums\VisiteStatut::class,
    ];

    // Attributs calculés renvoyés dans toArray()/JSON
    protected $appends = ['est_soldee'];

    protected static function booted(): void
    {
        static::creating(function (self $v) {
            if (! $v->id) $v->id = (string) Str::uuid();
            if (! $v->heure_arrivee) $v->heure_arrivee = now();

            // --- Tarification automatique ---
            // Si pas de tarif explicitement fourni, on prend celui du service (si défini comme tarif courant)
            if (! $v->tarif_id && $v->service_id && $v->service?->tarif) {
                $v->tarif_id = $v->service->tarif->id;
            }

            // Si on a un tarif, remplir montant/devise par défaut
            if ($v->tarif_id && is_null($v->montant_prevu)) {
                $v->montant_prevu = $v->tarif->prix;              // colonne 'prix' dans tarifs
                $v->devise        = $v->tarif->devise ?? 'CDF';   // par défaut CDF
            }

            // Montant dû = prévu au départ (sera ajusté par la caisse/paiements)
            if (is_null($v->montant_du)) {
                $v->montant_du = $v->montant_prevu ?? 0;
            }

            // Statut par défaut
            $v->statut ??= 'EN_ATTENTE'; // ou \App\Enums\VisiteStatut::EN_ATTENTE->value
        });
    }

    // ----------------
    // Relations
    // ----------------
    public function patient() { return $this->belongsTo(Patient::class); }
    public function service() { return $this->belongsTo(Service::class); }
    public function medecin() { return $this->belongsTo(User::class, 'medecin_id'); }
    public function agent()   { return $this->belongsTo(User::class, 'agent_id'); }
    public function tarif()   { return $this->belongsTo(Tarif::class); }
    public function facture() { return $this->hasOne(Facture::class); } // important pour le lien caisse

    // ----------------
    // Helpers métier
    // ----------------
    public function envoyerEnCaisse(): void
    {
        $this->update(['statut' => 'A_ENCAISSER']);
    }

    public function marquerPayee(): void
    {
        $this->update(['statut' => 'PAYEE']);
    }

    public function clore(): void
    {
        $this->update(['statut' => 'CLOTUREE', 'clos_at' => now()]);
    }

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
}
