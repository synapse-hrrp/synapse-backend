<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FactureLigne extends Model
{
    protected $fillable = [
        'facture_id','tarif_id','designation',
        'quantite','prix_unitaire','montant'
    ];

    protected $casts = [
        'quantite'      => 'decimal:2',
        'prix_unitaire' => 'decimal:2',
        'montant'       => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Calcul du montant si absent
        static::saving(function (self $l) {
            if (is_null($l->montant)) {
                $l->montant = (float)$l->quantite * (float)$l->prix_unitaire;
            }
        });

        // Recalcul facture après modif
        $recalc = fn(self $l) => $l->facture?->recalc();
        static::created($recalc);
        static::updated($recalc);
        static::deleted($recalc);
    }

    // Relations
    public function facture() { return $this->belongsTo(Facture::class); }
    public function tarif()   { return $this->belongsTo(Tarif::class); }
}
