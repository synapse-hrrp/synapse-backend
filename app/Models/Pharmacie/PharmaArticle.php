<?php

namespace App\Models\Pharmacie;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class PharmaArticle extends Model
{
    // === Paramètres code auto ===
    public const CODE_PREFIX_LENGTH = 4;
    public const CODE_PAD           = 3;
    public const CODE_START_AT      = 1;

    protected $fillable = [
        'dci_id','name','code','form','dosage','unit','pack_size',
        'is_active','min_stock','max_stock',
        'buy_price','sell_price','tax_rate',
        'image_path', // chemin sur le disque 'public'
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

    // ⬅️ Ajout de 'stock_status' ici (le reste inchangé)
    protected $appends = ['stock_on_hand', 'label', 'image_url', 'stock_status'];

    // ── Relations ──────────────────────────────────────────
    public function dci()       { return $this->belongsTo(Dci::class, 'dci_id'); }
    public function lots()      { return $this->hasMany(PharmaLot::class, 'article_id'); }
    public function movements() { return $this->hasMany(PharmaStockMovement::class, 'article_id'); }

    // ── Mutators ───────────────────────────────────────────
    public function setImagePathAttribute($value): void
    {
        $this->attributes['image_path'] = $value ?: null; // pas de chaîne vide en DB
    }

    // ── Accessors calculés ─────────────────────────────────
    public function getStockOnHandAttribute(): int
    {
        // si withSum('lots as stock_on_hand','quantity') a déjà posé une valeur
        if (array_key_exists('stock_on_hand', $this->attributes) && $this->attributes['stock_on_hand'] !== null) {
            return (int) $this->attributes['stock_on_hand'];
        }
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

    /**
     * URL d’image publique.
     * - Si image présente => renvoie une **URL absolue** basée sur ASSET_URL (priorité) sinon APP_URL,
     *   sinon (fallback) host de la requête.
     * - Si pas d’image => avatar SVG inline (data URI) → jamais cassé.
     */
    public function getImageUrlAttribute(): string
    {
        if (!empty($this->image_path)) {
            // /storage/... (ou absolue si 'url' est configuré sur le disk)
            $relativeOrAbsolute = Storage::disk('public')->url($this->image_path);

            // Si c'est déjà une URL absolue, on la renvoie telle quelle
            if (preg_match('#^https?://#i', $relativeOrAbsolute)) {
                return $relativeOrAbsolute;
            }

            // Base absolue: ASSET_URL > APP_URL > host courant
            $base = rtrim(
                (string) (config('app.asset_url') ?: config('app.url') ?: (request()?->getSchemeAndHttpHost() ?? '')),
                '/'
            );

            // Normalise le /storage/... (commence déjà par /)
            return $base . $relativeOrAbsolute;
        }

        // Fallback avatar inline (aucun appel réseau)
        $text = $this->initialsForAvatar();
        [$bg, $fg] = $this->avatarColors($this->name ?? $this->label ?? $this->code ?? 'Med');

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" role="img" aria-label="{$text}">
  <rect width="100%" height="100%" rx="24" ry="24" fill="{$bg}"/>
  <text x="50%" y="50%" dy=".1em" text-anchor="middle"
        font-family="system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,'Helvetica Neue',Arial"
        font-size="110" font-weight="700" fill="{$fg}">{$text}</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    // ⬅️ Nouvel accessor: statut stock (below_min | ok | above_max)
    public function getStockStatusAttribute(): string
    {
        $onHand = (int) $this->stock_on_hand;
        $min    = (int) ($this->min_stock ?? 0);
        $max    = (int) ($this->max_stock ?? 0);

        if ($min > 0 && $onHand < $min)  return 'below_min';
        if ($max > 0 && $onHand > $max)  return 'above_max';
        return 'ok';
    }

    protected function initialsForAvatar(): string
    {
        $base = trim((string) ($this->name ?: ($this->dci?->name ?: $this->code ?: 'M')));
        $ascii = Str::of($base)->ascii()->lower()->value();
        $words = preg_split('/[\s\-_.]+/u', $ascii, -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) return 'M';

        $letters = [];
        foreach ($words as $w) {
            if (isset($w[0]) && preg_match('/[a-z0-9]/i', $w[0])) $letters[] = strtoupper($w[0]);
            if (count($letters) === 2) break;
        }
        return implode('', $letters) ?: 'M';
    }

    protected function avatarColors(string $seed): array
    {
        $palette = [
            ['#2563eb', '#ffffff'], ['#16a34a', '#ffffff'],
            ['#d97706', '#ffffff'], ['#7c3aed', '#ffffff'],
            ['#dc2626', '#ffffff'], ['#0891b2', '#ffffff'],
            ['#f59e0b', '#1f2937'], ['#10b981', '#1f2937'],
            ['#3b82f6', '#ffffff'], ['#9333ea', '#ffffff'],
        ];
        $hash = crc32(Str::of($seed)->ascii()->value());
        return $palette[$hash % count($palette)];
    }

    // ── Scopes ─────────────────────────────────────────────
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

    // ====== Génération auto de code ======
    protected static function booted(): void
    {
        static::saving(function (PharmaArticle $article) {
            if (! $article->isDirty('min_stock')) {
                if ($article->min_stock === null || $article->min_stock === '' || (int)$article->min_stock === 0) {
                    $article->min_stock = 10;
                }
            }
            if (! $article->isDirty('max_stock')) {
                if ($article->max_stock === null || $article->max_stock === '' || (int)$article->max_stock === 0) {
                    // ✅ déjà monté à 1000 chez toi, on garde.
                    $article->max_stock = 1000;
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

            if ($article->isDirty('code') && filled($article->code)) {
                return;
            }

            $shouldRegen = blank($article->code) || $article->isDirty('name') || static::looksLikeLegacyUpperName($article);
            if ($shouldRegen) {
                $article->code = static::generateCodeFor($article);
            }
        });
    }

    protected static function looksLikeLegacyUpperName(self $article): bool
    {
        if (blank($article->code) || blank($article->name)) return false;
        $asciiName = Str::of($article->name)->ascii()->value();
        $nameLetters = preg_replace('/[^A-Za-z]/', '', $asciiName);
        if ($nameLetters === '') return false;
        return strtoupper($nameLetters) === strtoupper($article->code);
    }

    protected static function makeCodePrefix(PharmaArticle $article): string
    {
        $base = $article->name ?? $article->dci?->name ?? 'Med';
        $letters = preg_replace('/[^A-Za-z]/', '', Str::of($base)->ascii()->value());
        if ($letters === '') $letters = 'Med';
        $prefix = substr($letters, 0, static::CODE_PREFIX_LENGTH);
        return ucfirst(strtolower($prefix));
    }

    protected static function nextSequenceForPrefix(string $prefix): string
    {
        $lastCode = static::where('code', 'like', $prefix.'%')
            ->orderBy('code', 'desc')
            ->value('code');

        if ($lastCode && preg_match('/(\d+)$/', $lastCode, $m)) {
            $n = (int) $m[1] + 1;
        } else {
            $n = (int) static::CODE_START_AT;
        }

        return Str::padLeft((string) $n, static::CODE_PAD, '0');
    }

    protected static function generateCodeFor(PharmaArticle $article): string
    {
        $prefix = static::makeCodePrefix($article);
        for ($i = 0; $i < 5; $i++) {
            $seq  = static::nextSequenceForPrefix($prefix);
            $code = $prefix . $seq;
            if (! static::where('code', $code)->exists()) {
                return $code;
            }
        }
        return $prefix . Str::padLeft((string) random_int(1, 999), static::CODE_PAD, '0');
    }
}
