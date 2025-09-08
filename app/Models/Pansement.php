<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Pansement extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'pansements';

    protected $fillable = [
        'patient_id','visite_id',
        'date_soin','type','observation','etat_plaque','produits_utilises',
        'soignant_id','status'
    ];

    protected $casts = [
        'date_soin' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!$m->id)        $m->id = (string) Str::uuid();
            if (!$m->date_soin) $m->date_soin = now();
            if (!$m->status)    $m->status = 'en_cours';
        });
    }

    // Relations
    public function patient()  { return $this->belongsTo(Patient::class); }
    public function visite()   { return $this->belongsTo(Visite::class); }
    public function soignant() { return $this->belongsTo(User::class, 'soignant_id'); }
}
