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
        // Remplir le snapshot médecin (depuis Medecin -> Personnel) sans SELECT de full_name en SQL
        $fillDoctor = function (self $v): void {
            if ($v->medecin_id) {
                $med = \App\Models\Medecin::with('personnel:id,first_name,last_name')
                    ->find($v->medecin_id);

                if ($med && $med->personnel) {
                    $full = trim(($med->personnel->first_name ?? '').' '.($med->personnel->last_name ?? ''));
                    $v->medecin_nom = $full !== '' ? $full : ($v->medecin_nom ?: null);
                }
            } else {
                $v->medecin_nom = $v->medecin_nom ?: null;
            }
        };

        // Tarifs + defaults
        $fillPricing = function (self $v) {
            $isEmpty = static fn($x) => $x === null || $x === '' || (is_numeric($x) && (float)$x == 0.0);

            if (! $v->tarif_id && $v->service_id) {
                $service = $v->relationLoaded('service') ? $v->service : $v->service()->first();
                $tarifId = null;

                if ($service && $service->tarif) {
                    $tarifId = $service->tarif->id;
                } else {
                    $slug = $service?->slug ?? \App\Models\Service::whereKey($v->service_id)->value('slug');
                    if ($slug) {
                        $tarifId = \App\Models\Tarif::where('service_slug', $slug)
                            ->where('is_active', true)
                            ->orderByDesc('created_at')
                            ->value('id');
                    }
                }
                if ($tarifId) $v->tarif_id = $tarifId;
            }

            if ($v->tarif_id && $isEmpty($v->montant_prevu)) {
                $v->montant_prevu = (string) $v->tarif()->value('montant');
                $v->devise        = $v->tarif()->value('devise') ?? 'XAF';
            }
            if ($isEmpty($v->montant_du)) $v->montant_du = $v->montant_prevu ?? 0;

            if (! $v->heure_arrivee) $v->heure_arrivee = now();
            $v->statut ??= 'EN_ATTENTE';
        };

        static::creating(function (self $v) use ($fillDoctor, $fillPricing) {
            if (! $v->id) $v->id = (string) Str::uuid();
            $fillDoctor($v);
            $fillPricing($v);
        });

        static::updating(function (self $v) use ($fillDoctor, $fillPricing) {
            if ($v->isDirty('medecin_id') || $v->isDirty('medecin_nom')) $fillDoctor($v);
            if ($v->isDirty(['service_id','tarif_id','montant_prevu','montant_du','devise'])) $fillPricing($v);
        });
    }

    // Relations
    public function patient() { return $this->belongsTo(Patient::class); }
    public function service() { return $this->belongsTo(Service::class); }
    public function medecin() { return $this->belongsTo(Medecin::class, 'medecin_id'); }
    public function agent()   { return $this->belongsTo(User::class, 'agent_id'); }
    public function tarif()   { return $this->belongsTo(Tarif::class); }
    public function facture() { return $this->hasOne(Facture::class); }

    // Métier
    public function envoyerEnCaisse(): void { $this->update(['statut' => 'A_ENCAISSER']); }
    public function marquerPayee(): void    { $this->update(['statut' => 'PAYEE']); }
    public function clore(): void           { $this->update(['statut' => 'CLOTUREE', 'clos_at' => now()]); }

    // Scopes
    public function scopeAEncaisser($q) { return $q->where('statut', 'A_ENCAISSER'); }
    public function scopeNonSoldees($q) { return $q->where('montant_du', '>', 0); }

    // Attribut
    public function getEstSoldeeAttribute(): bool { return (float) $this->montant_du <= 0.0; }

    public function aru() { return $this->hasOne(\App\Models\Aru::class, 'visite_id'); }
}
