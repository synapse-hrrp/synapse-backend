<?php

namespace App\Http\Requests\Pharmacie;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'customer_id'   => ['nullable','uuid'], // si tu veux lier à un patient
            'notes'         => ['nullable','string','max:500'],
            'auto_invoice'  => ['sometimes','boolean'], // true => tenter création facture
        ];
    }
}
