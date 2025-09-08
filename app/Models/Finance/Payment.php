<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\User;

class Payment extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'payments';

    protected $fillable = [
        'id','invoice_id','montant','devise','methode','recu_par','date_paiement',
    ];

    protected $casts = [
        'date_paiement' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!$m->id)            $m->id = (string) Str::uuid();
            if (!$m->devise)        $m->devise = 'XOF';
            if (!$m->date_paiement) $m->date_paiement = now();
        });

        // Recalcul auto de la facture liée après chaque modif du paiement
        $recalc = function (self $m) {
            $inv = $m->invoice()->first();
            if ($inv && method_exists($inv, 'recomputeTotals')) {
                $inv->recomputeTotals();
            }
        };
        static::created($recalc);
        static::updated($recalc);
        static::deleted($recalc);
        static::restored($recalc);
    }

    public function invoice() { return $this->belongsTo(Invoice::class, 'invoice_id'); }
    public function recuPar() { return $this->belongsTo(User::class,    'recu_par'); }
}
