<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Personnel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'matricule',
        'first_name',
        'last_name',
        'sex',
        'date_of_birth',
        'cin',
        'phone_alt',   // téléphone secondaire uniquement ici
        'address',
        'city',
        'country',
        'job_title',
        'hired_at',
        'service_id',  // ✅ le service vit ici
        'avatar_path',
        'extra',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'hired_at'      => 'date',
        'extra'         => 'array',
    ];

    /** ➕ renvoie full_name dans les réponses JSON */
    protected $appends = ['full_name'];

    // ── Relations ──────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Service principal de la personne. */
    public function service()
    {
        return $this->belongsTo(Service::class);
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

    public function scopeMedecins($q)
    {
        // version tolérante aux accents/majuscules
        return $q->where(function($w) {
            $w->whereRaw('LOWER(job_title) LIKE ?', ['médecin%'])
              ->orWhereRaw('LOWER(job_title) LIKE ?', ['medecin%'])
              ->orWhereRaw('LOWER(job_title) LIKE ?', ['docteur%']);
        });
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    /** Nom complet RH */
    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }
}
