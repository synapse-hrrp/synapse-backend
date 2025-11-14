<?php

namespace App\Models;

use App\Models\Caisse\CashRegisterSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reglement extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'facture_id',
        'montant',
        'devise',
        'mode',
        'reference',

        // ✅ champs caisse
        'cashier_id',
        'cash_session_id',
        'workstation',
        'service_id', // ← recommandé par la spec (si colonne présente)
    ];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // UUID + devise auto
        static::creating(function (self $r) {
            if (! $r->id) {
                $r->id = (string) Str::uuid();
            }
            if (! $r->devise && $r->facture) {
                $r->devise = $r->facture->devise; // cohérent avec ta colonne "devise"
            }
            // Si la session est bornée à un service, force le service_id du règlement (optionnel)
            if (! $r->service_id && $r->cashSession && $r->cashSession->service_id) {
                $r->service_id = $r->cashSession->service_id;
            }
        });

        // Recalc après création/suppression/màj
        $recalc = fn(self $r) => $r->facture?->recalc();
        static::created($recalc);
        static::updated($recalc);
        static::deleted($recalc);
    }

    /* -------- Relations -------- */
    public function facture()     { return $this->belongsTo(Facture::class); }
    public function cashier()     { return $this->belongsTo(User::class, 'cashier_id'); }
    public function cashSession() { return $this->belongsTo(CashRegisterSession::class, 'cash_session_id'); }
    public function service()     { return $this->belongsTo(Service::class); } // si colonne présente
}
