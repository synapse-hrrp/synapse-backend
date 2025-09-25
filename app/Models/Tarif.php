<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Tarif extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'code',
        'libelle',
        'montant',
        'devise',
        'is_active',
        'service_slug',
    ];

    protected $casts = [
        'montant'   => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $t) {
            if (!$t->id) {
                $t->id = (string) Str::uuid();
            }
            if ($t->code) {
                $t->code = strtoupper(trim($t->code));
            }
            if (!$t->devise) {
                $t->devise = 'XAF';
            }
        });

        static::updating(function (self $t) {
            if ($t->code) {
                $t->code = strtoupper(trim($t->code));
            }
            if (!$t->devise) {
                $t->devise = 'XAF';
            }
        });
    }

    // Relation avec Service via le slug
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_slug', 'slug');
    }

    // Scopes
    public function scopeActifs($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeByCode($q, string $code)
    {
        return $q->where('code', strtoupper(trim($code)));
    }

    public function scopeForService($q, ?string $slug)
    {
        return $slug ? $q->where('service_slug', $slug) : $q;
    }
}
