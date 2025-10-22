<?php

namespace App\Models\Pharmacie;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PharmaLot extends Model
{
    protected $table = 'pharma_lots';

    protected $fillable = [
        'article_id', 'lot_number', 'expires_at', 'quantity',
        'buy_price', 'sell_price', 'supplier',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'quantity'   => 'integer',
        'buy_price'  => 'decimal:2',
        'sell_price' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Defaults: hériter des prix de l’article si absents
    |--------------------------------------------------------------------------
    */
    protected static function booted()
    {
        static::creating(function (PharmaLot $lot) {
            // Si on ne donne pas de prix → reprendre ceux de l’article
            if (is_null($lot->buy_price)) {
                $lot->buy_price = optional($lot->article)->buy_price;
            }
            if (is_null($lot->sell_price)) {
                $lot->sell_price = optional($lot->article)->sell_price;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */
    public function article()
    {
        return $this->belongsTo(PharmaArticle::class, 'article_id');
    }

    public function movements()
    {
        return $this->hasMany(PharmaStockMovement::class, 'lot_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes utiles
    |--------------------------------------------------------------------------
    */
    public function scopeAvailable(Builder $q)
    {
        return $q->where('quantity', '>', 0);
    }

    public function scopeNotExpired(Builder $q)
    {
        return $q->where(function ($w) {
            $w->whereNull('expires_at')
              ->orWhere('expires_at', '>=', Carbon::today());
        });
    }

    public function scopeFIFO(Builder $q)
    {
        // FEFO/FIFO: expiration d’abord (NULL en dernier), puis id
        return $q->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END, expires_at ASC, id ASC');
    }
}
