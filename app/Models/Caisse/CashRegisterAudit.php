<?php

namespace App\Models\Caisse;

use App\Models\Reglement;
use App\Models\User;
use App\Models\Facture; // ✅ ajouté
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRegisterAudit extends Model
{
    public const SESSION_OPENED   = 'SESSION_OPENED';
    public const PAYMENT_CREATED  = 'PAYMENT_CREATED';
    public const SESSION_CLOSED   = 'SESSION_CLOSED';

    protected $table = 'cash_register_audits';

    protected $fillable = [
        'event',
        'session_id',
        'user_id',
        'facture_id',
        'reglement_id',
        'workstation',
        'ip',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /* -------- Relations -------- */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reglement(): BelongsTo
    {
        return $this->belongsTo(Reglement::class, 'reglement_id');
    }

    public function facture(): BelongsTo // ✅ ajout ici
    {
        return $this->belongsTo(Facture::class, 'facture_id');
    }

    /* -------- Helper de log -------- */
    public static function log(string $event, CashRegisterSession $session, ?User $user, array $payload = [], array $meta = []): self
    {
        return static::create([
            'event'        => $event,
            'session_id'   => $session->id,
            'user_id'      => $user?->id,
            'workstation'  => $meta['workstation'] ?? $session->workstation ?? null,
            'ip'           => $meta['ip'] ?? null,
            'payload'      => $payload,
            'facture_id'   => $meta['facture_id'] ?? null,
            'reglement_id' => $meta['reglement_id'] ?? null,
        ]);
    }
}
