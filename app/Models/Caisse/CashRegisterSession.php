<?php

namespace App\Models\Caisse;

use App\Models\Reglement;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegisterSession extends Model
{
    protected $table = 'cash_register_sessions';

    protected $fillable = [
        'user_id',
        'workstation',
        'service_id',   // null => session générale
        'devise',       // si tu préfères "currency", renomme ici et en DB
        'opened_at',
        'closed_at',
        'opening_note',
        'closing_note',
        'payments_count',
        'total_amount',
    ];

    protected $casts = [
        'opened_at'      => 'datetime',
        'closed_at'      => 'datetime',
        'payments_count' => 'integer',
        'total_amount'   => 'decimal:2',
    ];

    /* -------- Scopes -------- */
    public function scopeOpen($q)   { return $q->whereNull('closed_at'); }
    public function scopeClosed($q) { return $q->whereNotNull('closed_at'); }

    /* -------- Relations -------- */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Reglement::class, 'cash_session_id');
    }

    /* -------- Helpers (optionnels mais pratiques) -------- */

    /** Vrai si session bornée à un service précis */
    public function isBoundToService(): bool
    {
        return ! is_null($this->service_id);
    }

    /** Devise par défaut de la session (fallback XAF si colonne devise absente) */
    public function getDefaultDevise(): string
    {
        // si tu utilises 'currency' en DB, adapte ici
        return $this->attributes['devise'] ?? 'XAF';
    }
}
