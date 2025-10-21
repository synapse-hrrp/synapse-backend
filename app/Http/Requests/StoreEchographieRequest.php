<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEchographieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Map tarif_code -> code_echo si non fourni
        if ($this->has('tarif_code') && ! $this->filled('code_echo')) {
            $this->merge([
                'code_echo' => strtoupper(trim((string) $this->input('tarif_code')))
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'patient_id'   => ['required','uuid','exists:patients,id'],
            'service_slug' => ['nullable','string','exists:services,slug'],

            // Le client envoie tarif_code, on le mappe vers code_echo
            'tarif_code'   => ['required','string','max:64'],

            // Champs métier autorisés
            'code_echo'    => ['sometimes','string','max:64'],
            'nom_echo'     => ['sometimes','nullable','string','max:255'],
            'indication'   => ['sometimes','nullable','string'],
            'demande_par'  => ['sometimes','nullable','integer','exists:personnels,id'],
            'date_demande' => ['sometimes','nullable','date'],

            // On n’accepte PAS ces champs côté client (calculés auto)
            // prix, devise, facture_id => unset en controller par sécurité
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        // On ne garde pas tarif_code en sortie de validation
        unset($data['tarif_code']);
        return $data;
    }
}
