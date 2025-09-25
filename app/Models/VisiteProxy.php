<?php
// app/Models/VisiteProxy.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class VisiteProxy extends Model
{
    use HasFactory;

    protected $table = 'visite_proxies';

    protected $fillable = [
        'visite_id',
        'service_slug',
        'patient_id',
        'medecin_id','medecin_nom',
        'agent_id','agent_nom',
        'heure_arrivee',
        'plaintes_motif','hypothese',
        'statut',
        'tarif_id','montant_prevu','montant_du','devise',
        'est_soldee',
        'source_created_at','source_updated_at',
        'raw',
    ];

    protected $attributes = [
        'devise'         => 'XAF',
        'est_soldee'     => false,
        'montant_prevu'  => 0,
        'montant_du'     => 0,
    ];

    protected $casts = [
        'heure_arrivee'     => 'datetime',
        'source_created_at' => 'immutable_datetime',
        'source_updated_at' => 'immutable_datetime',
        'montant_prevu'     => 'decimal:2', // renvoie string, exact pour les montants
        'montant_du'        => 'decimal:2',
        'est_soldee'        => 'boolean',
        'raw'               => 'array',
        'tarif_id'          => 'integer',   // 🔹 cohérent avec unsignedBigInteger en DB
    ];

    /*
    |--------------------------------------------------------------------------
    | Accessors / Mutators
    |--------------------------------------------------------------------------
    */
    protected function devise(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => $v ? strtoupper($v) : null
        );
    }

    protected function statut(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => $v ? trim((string) $v) : null
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */
    public function scopeService($q, string $slug)
    {
        return $q->where('service_slug', $slug);
    }

    public function scopePatient($q, string $patientId)
    {
        return $q->where('patient_id', $patientId);
    }

    public function scopeEntre($q, ?string $de = null, ?string $a = null)
    {
        return $q
            ->when($de, fn($qq) => $qq->where('heure_arrivee', '>=', $de))
            ->when($a,  fn($qq) => $qq->where('heure_arrivee', '<=', $a));
    }

    public function scopeNonSoldees($q)
    {
        return $q->where('est_soldee', false);
    }

    public function scopeStatut($q, string $statut)
    {
        return $q->where('statut', $statut);
    }

    public function scopeRecent($q, int $days = 7)
    {
        return $q->where('created_at', '>=', now()->subDays($days));
    }

    /*
    |--------------------------------------------------------------------------
    | Relations (optionnelles)
    |--------------------------------------------------------------------------
    */
    // public function patient() { return $this->belongsTo(User::class, 'patient_id'); }
    // public function medecin() { return $this->belongsTo(User::class, 'medecin_id'); }
    // public function agent()   { return $this->belongsTo(User::class, 'agent_id'); }
}
