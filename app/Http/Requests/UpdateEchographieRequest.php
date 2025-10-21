<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEchographieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tarif_code') && ! $this->filled('code_echo')) {
            $this->merge([
                'code_echo' => strtoupper(trim((string) $this->input('tarif_code')))
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'service_slug' => ['sometimes','nullable','string','exists:services,slug'],
            'tarif_code'   => ['sometimes','string','max:64'],

            'code_echo'    => ['sometimes','string','max:64'],
            'nom_echo'     => ['sometimes','nullable','string','max:255'],
            'indication'   => ['sometimes','nullable','string'],
            'statut'       => ['sometimes','nullable','in:en_attente,en_cours,termine,valide'],
            'demande_par'  => ['sometimes','nullable','integer','exists:personnels,id'],
            'date_demande' => ['sometimes','nullable','date'],
            'realise_par'  => ['sometimes','nullable','integer','exists:personnels,id'],
            'date_realisation' => ['sometimes','nullable','date'],
            'valide_par'   => ['sometimes','nullable','integer','exists:personnels,id'],
            'date_validation'  => ['sometimes','nullable','date'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        unset($data['tarif_code']);
        return $data;
    }
}
