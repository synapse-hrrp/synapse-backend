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
        'service_id',
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
            // Normalisations utiles
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

    // Relations
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    // Scopes pratiques (optionnels)
    public function scopeActifs($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeByCode($q, string $code)
    {
        return $q->where('code', strtoupper(trim($code)));
    }

    public function scopeForService($q, ?int $serviceId)
    {
        return $serviceId ? $q->where('service_id', $serviceId) : $q;
    }
}
