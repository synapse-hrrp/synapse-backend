<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // ← décommente quand Sanctum sera installé
use Spatie\Permission\Traits\HasRoles; // 👈 IMPORTANT

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;  // 👈 AJOUT HasApiTokens
    /**
     * Attributs assignables en masse.
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'service_id',
    ];

    /**
     * Attributs masqués pour la sérialisation.
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Valeurs par défaut.
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Casts / conversions de types.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'last_login_at'     => 'datetime',
            'is_active'         => 'boolean',
        ];
    }

    /** Toujours stocker l'email en minuscule et trim. */
    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value ? mb_strtolower(trim($value)) : null;
    }

    /** Scope : utilisateurs actifs uniquement. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Scope : recherche simple (nom / email / téléphone). */
    public function scopeSearch($query, ?string $term)
    {
        if (!$term) return $query;

        $like = '%' . preg_replace('/\s+/', '%', trim($term)) . '%';

        return $query->where(function ($q) use ($like) {
            $q->where('name', 'like', $like)
              ->orWhere('email', 'like', $like)
              ->orWhere('phone', 'like', $like);
        });
    }

    /** Services liés (plusieurs) via pivot service_user (avec is_primary). */
    public function services()
    {
        return $this->belongsToMany(Service::class)
            ->withTimestamps()
            ->withPivot('is_primary');
    }

    /** Service principal (si colonne users.service_id présente). */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function personnel()
    {
        return $this->hasOne(\App\Models\Personnel::class);
    }

}
