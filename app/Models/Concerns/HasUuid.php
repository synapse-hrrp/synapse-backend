<?php
namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasUuid {
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function bootHasUuid(): void {
        static::creating(function ($m) {
            if (! $m->getKey()) $m->{$m->getKeyName()} = (string) Str::uuid();
        });
    }
}
