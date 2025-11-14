<?php

namespace App\Models\Pharmacie;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartLine extends Model
{
    use HasFactory;

    protected $table = 'pharma_cart_lines';

    protected $fillable = ['cart_id','article_id','quantity','unit_price','tax_rate'];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'decimal:2',
        'tax_rate'   => 'decimal:2',
    ];

    public function cart()
    {
        return $this->belongsTo(PharmaCart::class, 'cart_id');
    }

    public function article()
    {
        return $this->belongsTo(PharmaArticle::class, 'article_id'); // ✅ correction
    }
}
