<?php

namespace App\Http\Requests\Pharmacie;

use Illuminate\Foundation\Http\FormRequest;

class CartAddRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'article_id' => ['required','exists:pharma_articles,id'],
            'quantity'   => ['required','integer','min:1'],
        ];
    }
}
