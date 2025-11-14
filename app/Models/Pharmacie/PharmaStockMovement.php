<?php

namespace App\Models\Pharmacie;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PharmaStockMovement extends Model
{
    protected $fillable = [
        'article_id','lot_id','type','quantity','unit_price','reason','reference','user_id',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Si aucune référence n’est fournie manuellement
            if (empty($model->reference)) {
                $prefix = strtoupper(substr($model->type ?? 'MVT', 0, 4)); // ex: RECE, SORT, ADJU, MVT

                $year  = date('Y');
                $month = date('m');

                // Chercher le dernier numéro pour ce type / mois / année
                $lastRef = self::where('reference', 'like', "{$prefix}-{$year}-{$month}-%")
                    ->orderByDesc('id')
                    ->value('reference');

                if ($lastRef && preg_match('/-(\d+)$/', $lastRef, $matches)) {
                    $next = (int)$matches[1] + 1;
                } else {
                    $next = 1;
                }

                $model->reference = sprintf('%s-%s-%s-%04d', $prefix, $year, $month, $next);
            }
        });
    }

    // ─── Relations ───────────────────────────
    public function article()
    {
        return $this->belongsTo(PharmaArticle::class, 'article_id');
    }

    public function lot()
    {
        return $this->belongsTo(PharmaLot::class, 'lot_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
