<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFactureLigneRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'designation'   => ['sometimes','string','max:255'],
            'quantite'      => ['sometimes','numeric','min:0.01'],
            'prix_unitaire' => ['sometimes','numeric','min:0'],
            'tarif_id'      => ['nullable','uuid','exists:tarifs,id'],
        ];
    }
}
