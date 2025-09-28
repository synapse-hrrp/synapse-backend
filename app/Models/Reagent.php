<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reagent extends Model
{
    protected $fillable = [
        'name','sku','uom','cas_number','hazard_class',
        'storage_temp_min','storage_temp_max','storage_conditions',
        'concentration','container_size','location_default',
        'min_stock','reorder_point','supplier_id'
    ];
    protected $casts = [
        'current_stock'=>'float',
        'storage_temp_min'=>'float',
        'storage_temp_max'=>'float',
        'min_stock'=>'float',
        'reorder_point'=>'float',
    ];

    public function lots(): HasMany { return $this->hasMany(ReagentLot::class); }
    public function movements(): HasMany { return $this->hasMany(StockMovement::class); }

    public function computeStock(): float {
        return (float) $this->lots()->sum('current_qty');
    }
}
