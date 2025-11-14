<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Medecin extends Model
{
    use HasFactory;

    protected $fillable = [
        'personnel_id',
        'numero_ordre',
        'specialite',
        'grade',
    ];

    protected $casts = [
        // ex: 'created_at' => 'datetime',
    ];

    protected $appends = ['display'];

    // ── Relations ──────────────────────────────────────────────────────────

    public function personnel()
    {
        return $this->belongsTo(Personnel::class);
    }

    /** Helper non-relation pour accéder rapidement à l’utilisateur */
    public function getUserAttribute()
    {
        return optional($this->personnel)->user;
    }

    public function plannings()
    {
        return $this->hasMany(MedecinPlanning::class);
    }

    public function planningExceptions()
    {
        return $this->hasMany(MedecinPlanningException::class);
    }

    public function rendezVous()
    {
        return $this->hasMany(RendezVous::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'medecin_service', 'medecin_id', 'service_slug', 'id', 'slug')
            ->withPivot(['is_active','slot_duration','capacity_per_slot'])
            ->withTimestamps();
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeBySpecialite($q, ?string $specialite)
    {
        return $specialite ? $q->where('specialite', $specialite) : $q;
    }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        $like = '%'.preg_replace('/\s+/', '%', trim($term)).'%';

        return $q->where(function ($w) use ($like) {
            $w->where('numero_ordre','like',$like)
              ->orWhere('specialite','like',$like)
              ->orWhere('grade','like',$like)
              ->orWhereHas('personnel', function ($p) use ($like) {
                  $p->where('first_name','like',$like)
                    ->orWhere('last_name','like',$like)
                    ->orWhere('matricule','like',$like);
              });
        });
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    public function getDisplayAttribute(): string
    {
        $name = optional($this->personnel)->full_name ?: '';
        $spec = $this->specialite ? " ({$this->specialite})" : '';
        return trim($name.$spec);
    }
}
