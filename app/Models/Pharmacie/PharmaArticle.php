<?php

namespace App\Models\Pharmacie;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PharmaArticle extends Model
{
    // == paramètres réglables ==
    public const CODE_PREFIX_LENGTH = 4; // nb de lettres pour "Medo"
    public const CODE_PAD           = 3; // "001" => 3 chiffres
    public const CODE_START_AT      = 1; // mets 500 pour commencer à ...501

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

    // ── Relations ──────────────────────────────────────────────────────────
    public function dci()       { return $this->belongsTo(Dci::class, 'dci_id'); }
    public function lots()      { return $this->hasMany(PharmaLot::class, 'article_id'); }
    public function movements() { return $this->hasMany(PharmaStockMovement::class, 'article_id'); }

    // ── Attributs calculés ─────────────────────────────────────────────────
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

    // ── Scopes ─────────────────────────────────────────────────────────────
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

    // ====== Génération / régénération automatique de code ======
    protected static function booted(): void
    {
        static::saving(function (PharmaArticle $article) {
            // Valeurs par défaut (sans écraser les entrées explicites)
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

            // name basé sur DCI si vide
            if (blank($article->name) && $article->dci?->name) {
                $article->name = $this->dci->name; // (sécurité)
            }

            // === Règles de (ré)génération du code ===
            // 1) Si le code est explicitement fourni dans la requête -> on respecte, on ne régénère pas.
            if ($article->isDirty('code') && filled($article->code)) {
                return;
            }

            $shouldRegen =
                // a) code vide / non fourni
                blank($article->code)
                // b) le nom change (on garde la cohérence nom/code)
                || $article->isDirty('name')
                // c) cas des anciens articles: code = NOM en majuscules (ex: "DOLADOLE")
                || static::looksLikeLegacyUpperName($article);

            if ($shouldRegen) {
                $article->code = static::generateCodeFor($article);
            }
        });
    }

    /**
     * Détecte l'ancien format "CODE = NAME en majuscules" (ASCII only).
     */
    protected static function looksLikeLegacyUpperName(self $article): bool
    {
        if (blank($article->code) || blank($article->name)) return false;

        $asciiName = Str::of($article->name)->ascii()->value();
        $nameLetters = preg_replace('/[^A-Za-z]/', '', $asciiName);
        if ($nameLetters === '') return false;

        return strtoupper($nameLetters) === strtoupper($article->code);
    }

    /**
     * Construit le préfixe (4 lettres ASCII, "Medo" style)
     */
    protected static function makeCodePrefix(PharmaArticle $article): string
    {
        $base = $article->name
            ?? $article->dci?->name
            ?? 'Med';

        // ASCII + lettres uniquement
        $letters = preg_replace('/[^A-Za-z]/', '', Str::of($base)->ascii()->value());
        if ($letters === '') $letters = 'Med';

        $prefix = substr($letters, 0, static::CODE_PREFIX_LENGTH);
        return ucfirst(strtolower($prefix)); // "Medo"
    }

    /**
     * Retourne le prochain numéro séquentiel (padding 3) pour un préfixe donné.
     */
    protected static function nextSequenceForPrefix(string $prefix): string
    {
        // On cherche le dernier code du même préfixe et on incrémente son suffixe numérique.
        $lastCode = static::where('code', 'like', $prefix.'%')
            ->whereRaw('code REGEXP ?', [$prefix.'[0-9]+$'])
            ->orderByRaw("CAST(SUBSTRING(code, ".(strlen($prefix)+1).") AS UNSIGNED) DESC")
            ->value('code');

        if ($lastCode && preg_match('/(\d+)$/', $lastCode, $m)) {
            $n = (int) $m[1] + 1;
        } else {
            $n = (int) static::CODE_START_AT; // ex: 500 pour démarrer à ...501
        }

        return Str::padLeft((string) $n, static::CODE_PAD, '0');
    }

    /**
     * Génère un code unique du type "Medo001".
     */
    protected static function generateCodeFor(PharmaArticle $article): string
    {
        $prefix = static::makeCodePrefix($article);

        // Boucle de sécurité (collisions improbables si index unique)
        for ($i = 0; $i < 5; $i++) {
            $seq  = static::nextSequenceForPrefix($prefix);
            $code = $prefix . $seq;

            if (! static::where('code', $code)->exists()) {
                return $code;
            }
        }

        // Fallback rarissime
        return $prefix . Str::padLeft((string) random_int(1, 999), static::CODE_PAD, '0');
    }
}
