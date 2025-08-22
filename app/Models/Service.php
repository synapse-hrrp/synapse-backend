<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;

    protected $fillable = ['slug','name','code','is_active'];

    // utilisateurs rattachés (0..N)
    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps()->withPivot('is_primary');
    }

    // utilisateurs dont c’est le service principal (si colonne users.service_id utilisée)
    public function primaryUsers()
    {
        return $this->hasMany(User::class);
    }
}
