<?php

namespace App\Models\Pharmacie;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PharmaArticle extends Model
{
    // == paramètres réglables ==
    public const CODE_PREFIX_LENGTH = 4; // nb de lettres pour "Medo"
    public const CODE_PAD           = 3; // "001"
    public const CODE_START_AT      = 1; // mettre 500 si tu veux démarrer à 501

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

    // ====== Génération automatique de code ======
    protected static function booted(): void
    {
        static::saving(function (PharmaArticle $article) {
            // Valeurs par défaut existantes...
            if (! $article->isDirty('min_stock')) {
                if ($article->min_stock === null || $article->min_stock === '' || (int)$article->min_stock === 0) {
                    $article->min_stock = 10;
                }
            }

            if (! $article->isDirty('max_stock')) {
                if ($article->max_stock === null || $article->max_stock === '' || (int)$article->max_stock === 0) {
                    $article->max_stock = 100;
                }
            }

            if (! $article->isDirty('pack_size')) {
                if ($article->pack_size === null || (int)$article->pack_size <= 0) {
                    $article->pack_size = 1;
                }
            }

            if (blank($article->name) && $article->dci?->name) {
                $article->name = $article->dci->name;
            }

            // === Auto-code si vide ou non fourni ===
            if (blank($article->code)) {
                $article->code = static::generateCodeFor($article);
            }
        });
    }

    protected static function generateCodeFor(PharmaArticle $article): string
    {
        $prefix = static::makeCodePrefix($article);
        // On tente et on gère une éventuelle collision par retry
        for ($i = 0; $i < 5; $i++) {
            $seq = static::nextSequenceForPrefix($prefix);
            $code = $prefix . $seq;
            // Si pas d’unicité en DB, on peut retourner direct.
            // Avec un index unique, on try/catch au save, mais on anticipe ici:
            $exists = static::where('code', $code)->exists();
            if (! $exists) return $code;
        }
        // fallback très rare
        return $prefix . Str::padLeft((string) random_int(1, 999), static::CODE_PAD, '0');
    }

    protected static function makeCodePrefix(PharmaArticle $article): string
    {
        $base = $article->name
            ?? $article->dci?->name
            ?? 'Med';

        // ASCII, lettres uniquement
        $letters = preg_replace('/[^A-Za-z]/', '', Str::of($base)->ascii()->value());
        if ($letters === '') $letters = 'Med';

        $prefix = substr($letters, 0, static::CODE_PREFIX_LENGTH);
        // Capitalize première lettre, reste en minuscule => "Medo"
        return ucfirst(strtolower($prefix));
    }

    protected static function nextSequenceForPrefix(string $prefix): string
    {
        // Récupère le suffixe numérique le plus grand pour ce préfixe
        // Exemple: "Medo501" -> 501
        $lastCode = static::where('code', 'like', $prefix.'%')
            ->whereRaw('code REGEXP ?', [$prefix.'[0-9]+$'])
            ->orderByRaw("CAST(SUBSTRING(code, ".(strlen($prefix)+1).") AS UNSIGNED) DESC")
            ->value('code');

        if ($lastCode) {
            $n = (int) preg_replace('/^\D+/', '', $lastCode) + 1;
        } else {
            $n = (int) static::CODE_START_AT; // 1 par défaut — mets 500 si tu veux commencer à 501
        }

        return Str::padLeft((string) $n, static::CODE_PAD, '0');
    }
}
