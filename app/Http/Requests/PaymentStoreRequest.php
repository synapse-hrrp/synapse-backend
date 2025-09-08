<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_id'    => ['required','uuid','exists:invoices,id'],
            'montant'       => ['required','numeric','min:0.01'],
            'devise'        => ['nullable','string','size:3'],
            'methode'       => ['nullable','in:cash,mobile_money,card,transfer'],
            'date_paiement' => ['nullable','date'],
        ];
    }
}
