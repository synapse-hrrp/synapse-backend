<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    // ── Hooks (auto-matricule) ─────────────────────────────────────────────
    protected static function booted(): void
    {
        static::creating(function (self $p) {
            if (empty($p->matricule)) {
                $p->matricule = self::generateYearlyServiceSequenceMatricule($p);
            }
        });
    }

    /**
     * Génère un matricule unique: HOP-YYYY-SER-0001
     * - YYYY : année courante
     * - SER  : 3 premières lettres du slug service (ou 'GEN' si inconnu)
     * - 0001 : compteur séquentiel qui REDÉMARRE chaque année et par service
     */
    protected static function generateYearlyServiceSequenceMatricule(self $p): string
    {
        $year = (string) now()->year;

        // Déterminer le code service (3 lettres) depuis le slug
        $serviceCode = 'GEN';
        if ($p->service_id) {
            $service = Service::find($p->service_id);
            if ($service && $service->slug) {
                $serviceCode = strtoupper(substr(Str::slug($service->slug), 0, 3));
            }
        }

        $prefix = "HOP-{$year}-{$serviceCode}-";

        // Récupérer le dernier matricule existant pour ce prefix (y compris soft-deleted)
        $last = static::withTrashed()
            ->where('matricule', 'like', $prefix.'%')
            ->orderBy('matricule', 'desc') // tri lexicographique OK car padding 4 chiffres
            ->value('matricule');

        // Extraire le dernier numéro et incrémenter
        $next = 1;
        if ($last && preg_match('/(\d{4})$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        // Boucle de sécurité en cas de collision (rare)
        do {
            $seq = str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $candidate = $prefix.$seq;
            $exists = static::withTrashed()->where('matricule', $candidate)->exists();
            $next++;
        } while ($exists);

        return $candidate;
    }

    // ── Relations ──────────────────────────────────────────────────────────
    public function user()     { return $this->belongsTo(User::class); }
    public function service()  { return $this->belongsTo(Service::class); }
    /** Profil médecin (si la personne est médecin) */
    public function medecin()  { return $this->hasOne(Medecin::class); }

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
    public function scopeMedecins($q) { return $q->whereHas('medecin'); }

    // ── Accessors ──────────────────────────────────────────────────────────
    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }
    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar_path) return null;
        return Storage::disk('public')->url($this->avatar_path);
    }
}
