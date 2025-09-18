<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReglementRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'montant'   => ['required','numeric','min:0.01'],
            'mode'      => ['required','string','max:20'], // CASH | MOMO | CARTE ...
            'reference' => ['nullable','string','max:100'],
        ];
    }
}
