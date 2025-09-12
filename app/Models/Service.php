<?php

// App/Models/Service.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;

    protected $fillable = ['slug','name','code','is_active'];
    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Route-model binding via slug (/services/{slug})
    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps()->withPivot('is_primary');
    }

    public function primaryUsers()
    {
        return $this->hasMany(User::class);
    }
}
