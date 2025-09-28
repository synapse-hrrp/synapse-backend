<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['name','path','is_cold_chain','temp_range_min','temp_range_max'];
    protected $casts = ['is_cold_chain'=>'bool','temp_range_min'=>'float','temp_range_max'=>'float'];
}
