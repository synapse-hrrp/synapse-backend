<?php

namespace App\Models\Pharmacie;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PharmaCart extends Model
{
    use HasFactory;

    protected $table = 'pharma_carts';

    protected $fillable = [
        'user_id',
        'status',
        'visite_id',      // permet de relier à la visite à l’origine du panier
        'patient_id',
        'customer_name',
        'total_ht',
        'total_tva',
        'total_ttc',
        'currency',
        'invoice_id',     // <-- on autorise le remplissage au checkout
    ];

    protected $casts = [
        'total_ht'  => 'decimal:2',
        'total_tva' => 'decimal:2',
        'total_ttc' => 'decimal:2',
    ];

    // Relations
    public function lines()
    {
        return $this->hasMany(PharmaCartLine::class, 'cart_id');
    }

    public function visite()
    {
        return $this->belongsTo(\App\Models\Visite::class, 'visite_id');
    }

    public function invoice()
    {
        return $this->belongsTo(\App\Models\Facture::class, 'invoice_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function patient()
    {
        return $this->belongsTo(\App\Models\Patient::class, 'patient_id');
    }

    /**
     * Recalcule les totaux du panier à partir des lignes.
     */
    public function recomputeTotals(): void
    {
        $sum = $this->lines()
            ->selectRaw('COALESCE(SUM(line_ht),0)  as ht')
            ->selectRaw('COALESCE(SUM(line_tva),0) as tva')
            ->selectRaw('COALESCE(SUM(line_ttc),0) as ttc')
            ->first();

        $this->forceFill([
            'total_ht'  => $sum->ht,
            'total_tva' => $sum->tva,
            'total_ttc' => $sum->ttc,
        ])->saveQuietly();
    }
}
