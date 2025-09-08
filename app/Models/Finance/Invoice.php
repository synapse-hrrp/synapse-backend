<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\User;

class Invoice extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'invoices';

    protected $fillable = [
        'id','numero','patient_id','visite_id',
        'devise','remise','montant_total','montant_paye','statut_paiement',
        'cree_par',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!$m->id)     $m->id = (string) Str::uuid();
            if (!$m->numero) $m->numero = static::nextNumero();
            if (!$m->devise) $m->devise = 'XOF';
            if (!$m->statut_paiement) $m->statut_paiement = 'unpaid';
        });
    }

    public static function nextNumero(): string
    {
        $prefix = 'INV-'.now()->year.'-';
        $last = static::withTrashed()
            ->where('numero','like',$prefix.'%')
            ->orderByDesc('numero')
            ->value('numero');

        $seq = $last ? ((int)substr($last, -6)) + 1 : 1;
        return $prefix . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
    }

    // Relations
    public function lignes()    { return $this->hasMany(InvoiceItem::class, 'invoice_id'); }
    public function paiements() { return $this->hasMany(Payment::class,     'invoice_id'); }

    public function creePar()   { return $this->belongsTo(User::class, 'cree_par'); }

    // Recalculer total / payé / statut
    public function recomputeTotals(): void
    {
        $totalItems = (float) $this->lignes()->sum('total_ligne');
        $remise     = (float) ($this->remise ?? 0);
        $total      = max(0, $totalItems - $remise);
        $paye       = (float) $this->paiements()->sum('montant');

        $this->montant_total  = $total;
        $this->montant_paye   = $paye;
        $this->statut_paiement = ($paye <= 0)
            ? 'unpaid'
            : ($paye < $total ? 'partial' : 'paid');

        $this->save();
    }
}
