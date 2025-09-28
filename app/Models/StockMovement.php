<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'reagent_id','reagent_lot_id','location_id',
        'type','quantity','moved_at','reference','unit_cost','user_id','notes'
    ];
    protected $casts = ['quantity'=>'float','unit_cost'=>'float','moved_at'=>'datetime'];

    public function reagent(): BelongsTo { return $this->belongsTo(Reagent::class); }
    public function lot(): BelongsTo { return $this->belongsTo(ReagentLot::class, 'reagent_lot_id'); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
}
