<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'        => ['required','uuid','exists:patients,id'],
            'visite_id'         => ['nullable','uuid','exists:visites,id'],
            'devise'            => ['nullable','string','size:3'],
            'remise'            => ['nullable','numeric','min:0'],

            'lignes'                    => ['required','array','min:1'],
            'lignes.*.service_slug'     => ['required','string','max:50'],
            'lignes.*.reference_id'     => ['nullable','uuid'],
            'lignes.*.libelle'          => ['required','string','max:190'],
            'lignes.*.quantite'         => ['nullable','integer','min:1'],
            'lignes.*.prix_unitaire'    => ['required','numeric','min:0'],
        ];
    }
}
