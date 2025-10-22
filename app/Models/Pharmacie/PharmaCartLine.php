<?php

namespace App\Models\Pharmacie;

use Illuminate\Database\Eloquent\Model;

class PharmaCartLine extends Model
{
    protected $fillable = [
        'cart_id','article_id','quantity','unit_price','tax_rate',
        'line_ht','line_tva','line_ttc',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'decimal:2',
        'tax_rate'   => 'decimal:2',
        'line_ht'    => 'decimal:2',
        'line_tva'   => 'decimal:2',
        'line_ttc'   => 'decimal:2',
    ];

    public function cart()    { return $this->belongsTo(PharmaCart::class, 'cart_id'); }
    public function article() { return $this->belongsTo(PharmaArticle::class, 'article_id'); }

    protected static function booted(): void
    {
        // 1) Avant sauvegarde : auto-remplir unit_price/tax_rate si non envoyés
        static::saving(function (PharmaCartLine $line) {
            // Assure une quantité valide
            $line->quantity = max((int)($line->quantity ?? 1), 1);

            // Charger l'article si besoin
            if ($line->isDirty('article_id') || ! $line->relationLoaded('article')) {
                $line->loadMissing('article');
            }

            // Si unit_price n'est pas fourni => copie depuis l'article
            if (! $line->isDirty('unit_price') && $line->article) {
                $line->unit_price = $line->article->sell_price;
            }

            // Si tax_rate n'est pas fourni => copie depuis l'article (ou 0)
            if (! $line->isDirty('tax_rate') && $line->article) {
                $line->tax_rate = $line->article->tax_rate ?? 0;
            }
            if ($line->tax_rate === null) {
                $line->tax_rate = 0;
            }

            // Calcul des totaux ligne
            $q   = (int) $line->quantity;
            $pu  = (float) ($line->unit_price ?? 0);
            $tx  = (float) ($line->tax_rate ?? 0);

            $ht  = $pu * $q;
            $tva = $ht * ($tx / 100);
            $ttc = $ht + $tva;

            $line->line_ht  = round($ht, 2);
            $line->line_tva = round($tva, 2);
            $line->line_ttc = round($ttc, 2);
        });

        // 2) Après sauvegarde / suppression : recalcule le panier
        static::saved(function (PharmaCartLine $line) {
            $line->cart?->recomputeTotals();
        });

        static::deleted(function (PharmaCartLine $line) {
            $line->cart?->recomputeTotals();
        });
    }
}
