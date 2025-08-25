<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Personnel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'matricule',
        'first_name',
        'last_name',
        'sex',
        'date_of_birth',
        'cin',
        'phone_alt',
        'address',
        'city',
        'country',
        'job_title',
        'hired_at',
        'service_id',
        'avatar_path',
        'extra',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'hired_at'      => 'date',
        'extra'         => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        $like = '%'.preg_replace('/\s+/', '%', trim($term)).'%';
        return $q->where(fn($w)=>
            $w->where('matricule','like',$like)
              ->orWhere('last_name','like',$like)
              ->orWhere('first_name','like',$like)
              ->orWhere('cin','like',$like)
        );
    }
}
