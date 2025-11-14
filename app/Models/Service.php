<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = ['slug','name','is_active','config'];
    protected $casts    = ['is_active'=>'boolean','config'=>'array'];

    public function getRouteKeyName(): string { return 'slug'; }

    // Visites: on garde service_id sur la table visites
    public function visites(){ return $this->hasMany(Visite::class); }

    // Tous les tarifs de ce service (FK = service_slug sur tarifs, clé locale = slug)
    public function tarifs()
    {
        return $this->hasMany(Tarif::class, 'service_slug', 'slug');
    }

    // Tarif “courant” (actif le plus récent)
    public function tarif()
    {
        return $this->hasOne(Tarif::class, 'service_slug', 'slug')
                    ->where('is_active', true)
                    ->latestOfMany(); // sinon ->orderByDesc('created_at')
    }

    public function scopeActive($q){ return $q->where('is_active', true); }
    public function scopeSlug($q, string $slug){ return $q->where('slug', $slug); }

    // Config helpers
    public function detailModelClass(): ?string { return $this->config['detail_model'] ?? null; }
    public function detailFk(): string         { return $this->config['detail_fk'] ?? 'visite_id'; }
    public function queueRoute(): ?string      { return $this->config['queue_route'] ?? null; }

    // Relations complémentaires
    public function medecins()
    {
        return $this->belongsToMany(Medecin::class, 'medecin_service', 'service_slug', 'medecin_id', 'slug', 'id')
            ->withPivot(['is_active','slot_duration','capacity_per_slot'])
            ->withTimestamps();
    }

    public function rendezVous()
    {
        return $this->hasMany(RendezVous::class, 'service_slug', 'slug');
    }
}
