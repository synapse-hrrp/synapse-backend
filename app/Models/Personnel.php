<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Personnel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'matricule',
        'first_name',
        'last_name',
        'sex',
        'date_of_birth',
        'cin',
        'phone_alt',
        'address',
        'city',
        'country',
        'job_title',
        'hired_at',
        'service_id',
        'avatar_path',
        'extra',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'hired_at'      => 'date',
        'extra'         => 'array',
    ];

    protected $appends = ['full_name', 'avatar_url'];

    // ── Relations ──────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /** Profil médecin (si la personne est médecin) */
    public function medecin()
    {
        return $this->hasOne(Medecin::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;

        $like = '%'.preg_replace('/\s+/', '%', trim($term)).'%';

        return $q->where(function ($w) use ($like) {
            $w->where('matricule','like',$like)
              ->orWhere('last_name','like',$like)
              ->orWhere('first_name','like',$like)
              ->orWhere('cin','like',$like);
        });
    }

    /** Filtre uniquement les personnels qui ont un profil médecin */
    public function scopeMedecins($q)
    {
        return $q->whereHas('medecin');
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) return null;
        return Storage::disk('public')->url($this->avatar_path);
    }
}
