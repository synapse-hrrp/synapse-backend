<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ServiceExamEvent extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id','service_slug','examen_id','action','actor_user_id','meta'
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($m) {
            if (!$m->id) $m->id = (string) Str::uuid();
        });
    }

    public function examen() { return $this->belongsTo(Examen::class); }
    public function service() { return $this->belongsTo(Service::class, 'service_slug', 'slug'); }
}
