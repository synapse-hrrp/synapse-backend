<?php

namespace App\Http\Requests\Pharmacie;

use Illuminate\Foundation\Http\FormRequest;

class LotRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'article_id' => ['required','exists:pharma_articles,id'],
            'lot_number' => ['required','string','max:100'],
            'expires_at' => ['nullable','date'],
            'quantity'   => ['required','integer','min:0'],
            'buy_price'  => ['nullable','numeric','min:0'],
            'sell_price' => ['nullable','numeric','min:0'],
            'supplier'   => ['nullable','string','max:190'],
        ];
    }
}
