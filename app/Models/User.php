<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'last_login_at',
        'last_login_ip',
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

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value ? mb_strtolower(trim($value)) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (!$term) return $query;
        $like = '%'.preg_replace('/\s+/', '%', trim($term)).'%';

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

    public function personnel()
    {
        return $this->hasOne(Personnel::class);
    }

    public function isMedecin(): bool
    {
        return (bool) optional($this->personnel)->medecin;
    }

    /**
     * Services autorisés (pivot user_service).
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'user_service', 'user_id', 'service_id');
        // ->withTimestamps(); // décommente si le pivot a timestamps
    }
}
