<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'last_login_at',
        'last_login_ip',
        // ❌ plus de service_id ici
    ];

    protected $hidden = ['password','remember_token'];

    protected $attributes = ['is_active' => true];

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

    /** Scope : recherche simple (nom / email / téléphone + champs personnel). */
    public function scopeSearch($query, ?string $term)
    {
        if (!$term) return $query;

        $like = '%' . preg_replace('/\s+/', '%', trim($term)) . '%';

        return $query->where(function ($q) use ($like) {
            $q->where('name', 'like', $like)
              ->orWhere('email', 'like', $like)
              ->orWhere('phone', 'like', $like)
              ->orWhereHas('personnel', function ($p) use ($like) {
                  $p->where('first_name','like',$like)
                    ->orWhere('last_name','like',$like)
                    ->orWhere('matricule','like',$like)
                    ->orWhere('cin','like',$like);
              });
        });
    }

    /** Relation 1–1 vers la fiche RH. */
    public function personnel()
    {
        return $this->hasOne(\App\Models\Personnel::class);
    }

    // ❌ On supprime:
    // - services() (belongsToMany)
    // - service()  (belongsTo avec users.service_id)
}
