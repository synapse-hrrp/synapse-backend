<?php

namespace App\Models\Pharmacie;

use Illuminate\Database\Eloquent\Model;

class Dci extends Model
{
    protected $fillable = ['name','description'];

    public function articles()
    {
        return $this->hasMany(PharmaArticle::class, 'dci_id');
    }
}
