<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFactureLigneRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'designation'   => ['required','string','max:255'],
            'quantite'      => ['required','numeric','min:0.01'],
            'prix_unitaire' => ['required','numeric','min:0'],
            'tarif_id'      => ['nullable','uuid','exists:tarifs,id'],
        ];
    }
}
