<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Laboratoire extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'laboratoires';

    protected $fillable = [
        'patient_id','visite_id',
        'test_code','test_name','specimen',
        'status',
        'result_value','unit','ref_range','result_json',
        'price','currency','invoice_id',
        'requested_by','requested_at',
        'validated_by','validated_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'validated_at' => 'datetime',
        'result_json'  => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!$m->id) $m->id = (string) Str::uuid();
            if (!$m->requested_at) $m->requested_at = now();
            if (!$m->status) $m->status = 'pending';
        });
    }

    public function patient() { return $this->belongsTo(Patient::class); }
    public function visite()  { return $this->belongsTo(Visite::class); }
    public function requester(){ return $this->belongsTo(User::class, 'requested_by'); }
    public function validator(){ return $this->belongsTo(User::class, 'validated_by'); }
}
