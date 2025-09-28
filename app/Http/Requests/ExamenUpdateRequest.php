<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamenUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id'           => ['sometimes','uuid','exists:patients,id'],
            'service_slug'         => ['sometimes','nullable','string','exists:services,slug'],
            'type_origine'         => ['sometimes','nullable', Rule::in(['interne','externe'])],

            'code_examen'          => ['sometimes','string','max:255'],
            'nom_examen'           => ['sometimes','string','max:255'],
            'prelevement'          => ['sometimes','nullable','string','max:255'],

            'statut'               => ['sometimes', Rule::in(['en_attente','en_cours','termine','valide'])],

            'valeur_resultat'      => ['sometimes','nullable','string','max:255'],
            'unite'                => ['sometimes','nullable','string','max:255'],
            'intervalle_reference' => ['sometimes','nullable','string','max:255'],
            'resultat_json'        => ['sometimes','nullable','array'],

            'prix'                 => ['sometimes','nullable','numeric'],
            'devise'               => ['sometimes','nullable','string','max:10'],
            'facture_id'           => ['sometimes','nullable','uuid'],

            'demande_par'          => ['sometimes','nullable','integer','exists:personnels,id'],
            'date_demande'         => ['sometimes','nullable','date'],

            'valide_par'           => ['sometimes','nullable','integer','exists:personnels,id'],
            'date_validation'      => ['sometimes','nullable','date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'service_slug' => 'service',
            'code_examen' => 'code de l’examen',
            'nom_examen' => 'nom de l’examen',
            'prelevement' => 'prélèvement',
        ];
    }
}
