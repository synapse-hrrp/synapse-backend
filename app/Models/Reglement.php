<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reglement extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'facture_id','montant','devise','mode','reference'
    ];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // UUID + devise auto
        static::creating(function (self $r) {
            if (! $r->id) $r->id = (string) Str::uuid();
            if (! $r->devise && $r->facture) {
                $r->devise = $r->facture->devise;
            }
        });

        // Recalc après création/suppression/màj
        $recalc = fn(self $r) => $r->facture?->recalc();
        static::created($recalc);
        static::updated($recalc);
        static::deleted($recalc);
    }

    // Relations
    public function facture() { return $this->belongsTo(Facture::class); }
}
