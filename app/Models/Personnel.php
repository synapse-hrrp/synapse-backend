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

    /**
     * URL absolue de l’avatar (alignée sur la stratégie "pharmacie").
     * - Si avatar_path est relatif (ex: "public/avatars/a.jpg" ou "avatars/a.jpg"),
     *   on renvoie "APP_URL/storage/avatars/a.jpg".
     * - Si c'est déjà une URL (http/https/data/blob), on la renvoie telle quelle.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        $path = $this->avatar_path;
        if (!$path) return null;

        $p = trim((string) $path);

        // Déjà une URL absolue ou data/blob ?
        if (preg_match('#^(https?:)?//|^(data:|blob:)#i', $p)) {
            return $p;
        }

        // Normaliser le chemin pour qu'il devienne "avatars/xxx.jpg"
        $p = ltrim($p, '/');
        if (str_starts_with($p, 'public/')) {
            $p = substr($p, strlen('public/'));   // public/avatars/a.jpg -> avatars/a.jpg
        }
        if (str_starts_with($p, 'storage/')) {
            $p = substr($p, strlen('storage/'));  // storage/avatars/a.jpg -> avatars/a.jpg
        }

        // Construire une URL ABSOLUE basée sur APP_URL
        $base = rtrim(config('app.url') ?: env('APP_URL', ''), '/'); // ex: http://192.168.1.176:8000
        if ($base === '') {
            // fallback Storage si jamais APP_URL n'est pas défini
            return Storage::disk('public')->url($p); // peut être relatif si APP_URL manquant
        }

        return $base . '/storage/' . $p; // http://host:port/storage/avatars/a.jpg
    }



    // App\Models\Personnel.php

    public function reglementsAsCashier()
    {
        return $this->hasMany(Reglement::class, 'cashier_id');
    }

}
