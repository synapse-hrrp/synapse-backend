<?php

// app/Models/FactureItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FactureItem extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'facture_items';

    protected $fillable = [
        'patient_id','facture_id',
        'tarif_id','tarif_code',
        'designation','quantite','prix_unitaire','total','remise','devise',
        'type_origine','created_by_user_id',
    ];

    protected $casts = [
        'quantite'      => 'integer',
        'prix_unitaire' => 'decimal:2',
        'total'         => 'decimal:2',
        'remise'        => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $i) {
            if (! $i->getKey()) $i->id = (string) Str::uuid();
            $i->devise  ??= 'XAF';
            $i->quantite = (int) ($i->quantite ?: 1);

            // Calcul auto du total
            $pu    = (float) ($i->prix_unitaire ?? 0);
            $qte   = (int)   ($i->quantite ?? 1);
            $rem   = (float) ($i->remise ?? 0);
            $i->total = max(0, $pu * $qte - $rem);
        });

        static::updating(function (self $i) {
            $i->quantite = (int) ($i->quantite ?: 1);
            $pu    = (float) ($i->prix_unitaire ?? 0);
            $qte   = (int)   ($i->quantite ?? 1);
            $rem   = (float) ($i->remise ?? 0);
            $i->total = max(0, $pu * $qte - $rem);
        });
    }

    // Relations
    public function facture() { return $this->belongsTo(Facture::class); }
    public function tarif()   { return $this->belongsTo(Tarif::class); }
    public function patient() { return $this->belongsTo(Patient::class); }
}
