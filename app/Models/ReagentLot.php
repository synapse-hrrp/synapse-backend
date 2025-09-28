<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReagentLot extends Model
{
    protected $fillable = [
        'reagent_id','lot_code','expiry_date','received_at',
        'initial_qty','current_qty','location_id','status','coa_url','barcode'
    ];
    protected $casts = [
        'initial_qty'=>'float',
        'current_qty'=>'float',
        'received_at'=>'datetime',
        'expiry_date'=>'date',
    ];

    public function reagent(): BelongsTo { return $this->belongsTo(Reagent::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function movements(): HasMany { return $this->hasMany(StockMovement::class, 'reagent_lot_id'); }
}
