<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEchographieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // à restreindre si besoin
    }

    public function rules(): array
    {
        return [
            'patient_id'     => ['required', 'exists:patients,id'],
            'service_slug'   => ['nullable', 'string', 'exists:services,slug'],
            'code_echo'      => ['nullable', 'string', 'max:50'],
            'nom_echo'       => ['nullable', 'string', 'max:255'],
            'indication'     => ['nullable', 'string'],
            'prix'           => ['nullable', 'numeric', 'min:0'],
            'devise'         => ['nullable', 'string', 'max:10'],
            'compte_rendu'   => ['nullable', 'string'],
            'conclusion'     => ['nullable', 'string'],
            'mesures_json'   => ['nullable', 'array'],
            'images_json'    => ['nullable', 'array'],
            'demande_par'    => ['nullable', 'exists:personnels,id'],
            'realise_par'    => ['nullable', 'exists:personnels,id'],
            'valide_par'     => ['nullable', 'exists:personnels,id'],
            'facture_id'     => ['nullable', 'exists:factures,id'],
            'date_demande'   => ['nullable', 'date'],
            'date_realisation' => ['nullable', 'date'],
            'date_validation'  => ['nullable', 'date'],
        ];
    }
}
