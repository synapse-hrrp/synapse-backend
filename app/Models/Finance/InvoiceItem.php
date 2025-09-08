<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InvoiceItem extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'invoice_items';

    protected $fillable = [
        'id','invoice_id','service_slug','reference_id','libelle',
        'quantite','prix_unitaire','total_ligne',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!$m->id) $m->id = (string) Str::uuid();
            $m->quantite     = $m->quantite     ?: 1;
            $m->total_ligne  = $m->total_ligne  ?: ($m->quantite * $m->prix_unitaire);
        });
    }

    public function invoice() { return $this->belongsTo(Invoice::class, 'invoice_id'); }
}
