<?php

namespace App\Models\Pharmacie;

use Illuminate\Database\Eloquent\Model;

class PharmaStockMovement extends Model
{
    protected $fillable = [
        'article_id','lot_id','type','quantity','unit_price','reason','reference','user_id',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function article() { return $this->belongsTo(PharmaArticle::class, 'article_id'); }
    public function lot()     { return $this->belongsTo(PharmaLot::class, 'lot_id'); }
    public function user()    { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
}
