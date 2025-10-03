<?php

// app/Http/Requests/FactureItemUpdateRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FactureItemUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Non modifiables après création
            'patient_id'  => ['prohibited'],
            'facture_id'  => ['prohibited'],
            'tarif_id'    => ['prohibited'],
            'tarif_code'  => ['prohibited'],

            // Modifiables
            'designation'       => ['sometimes','nullable','string','max:255'],
            'quantite'      => ['sometimes','nullable','integer','min:1'],
            'prix_unitaire' => ['sometimes','nullable','numeric','min:0'],
            'remise'        => ['sometimes','nullable','numeric','min:0'],
            'devise'        => ['sometimes','nullable','string','size:3'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'devise'   => $this->devise !== null ? strtoupper(trim($this->devise)) : null,
            'designation'  => $this->libelle !== null ? trim($this->libelle) : null,
        ]);
    }
}
