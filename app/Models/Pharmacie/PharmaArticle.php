<?php

namespace App\Models\Pharmacie;

use Illuminate\Database\Eloquent\Model;

class PharmaArticle extends Model
{
    protected $fillable = [
        'dci_id','name','code','form','dosage','unit','pack_size',
        'is_active','min_stock','max_stock',
        'buy_price','sell_price','tax_rate',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'pack_size'  => 'integer',
        'min_stock'  => 'integer',
        'max_stock'  => 'integer',
        'buy_price'  => 'decimal:2',
        'sell_price' => 'decimal:2',
        'tax_rate'   => 'decimal:2',
    ];

    protected $appends = ['stock_on_hand', 'label'];

    // Relations
    public function dci()       { return $this->belongsTo(Dci::class, 'dci_id'); }
    public function lots()      { return $this->hasMany(PharmaLot::class, 'article_id'); }
    public function movements() { return $this->hasMany(PharmaStockMovement::class, 'article_id'); }

    // Attributs calculés
    public function getStockOnHandAttribute(): int
    {
        return (int) $this->lots()->sum('quantity');
    }

    public function getLabelAttribute(): string
    {
        $parts = [];
        if ($this->dci?->name) $parts[] = $this->dci->name;
        if ($this->dosage)     $parts[] = trim($this->dosage . ' ' . ($this->unit ?? ''));
        $label = trim(implode(' ', array_filter($parts)));
        if ($this->form) $label .= ' (' . $this->form . ')';
        return $label !== '' ? $label : ($this->name ?? $this->code ?? 'Article');
    }

    // Scopes
    public function scopeActive($q) { return $q->where('is_active', true); }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        $like = '%'.preg_replace('/\s+/', '%', trim($term)).'%';
        return $q->where(function($w) use ($like) {
            $w->where('name','like',$like)
              ->orWhere('code','like',$like)
              ->orWhere('dosage','like',$like)
              ->orWhere('form','like',$like)
              ->orWhereHas('dci', fn($d)=>$d->where('name','like',$like));
        });
    }

    // Hooks automatiques
    protected static function booted(): void
    {
        static::saving(function (PharmaArticle $article) {

            // Valeurs par défaut (création ET update)
            // - Si le champ N’A PAS été envoyé (not dirty) et que la valeur est vide/0 → impose la valeur par défaut
            // - Si le champ A été envoyé (dirty), on respecte la valeur envoyée (même 0)

            // min_stock
            if (! $article->isDirty('min_stock')) {
                if ($article->min_stock === null || $article->min_stock === '' || (int)$article->min_stock === 0) {
                    $article->min_stock = 10;
                }
            }

            // max_stock
            if (! $article->isDirty('max_stock')) {
                if ($article->max_stock === null || $article->max_stock === '' || (int)$article->max_stock === 0) {
                    $article->max_stock = 100;
                }
            }

            // pack_size minimal
            if (! $article->isDirty('pack_size')) {
                if ($article->pack_size === null || (int)$article->pack_size <= 0) {
                    $article->pack_size = 1;
                }
            }

            // name basé sur DCI si vide
            if (blank($article->name) && $article->dci?->name) {
                $article->name = $article->dci->name;
            }
        });
    }
}
