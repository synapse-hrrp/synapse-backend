<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamenStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // adapte selon tes politiques d’authz
    }

    public function rules(): array
    {
        return [
            'patient_id'           => ['required','uuid','exists:patients,id'],
            'service_slug'         => ['nullable','string','exists:services,slug'],
            'type_origine'         => ['nullable', Rule::in(['interne','externe'])],

            'code_examen'          => ['required','string','max:255'],
            'nom_examen'           => ['required','string','max:255'],
            'prelevement'          => ['nullable','string','max:255'],

            'statut'               => ['nullable', Rule::in(['en_attente','en_cours','termine','valide'])],

            'valeur_resultat'      => ['nullable','string','max:255'],
            'unite'                => ['nullable','string','max:255'],
            'intervalle_reference' => ['nullable','string','max:255'],
            'resultat_json'        => ['nullable','array'],

            'prix'                 => ['nullable','numeric'],
            'devise'               => ['nullable','string','max:10'],
            'facture_id'           => ['nullable','uuid'], // mets exists:factures,id si table factures

            'demande_par'          => ['nullable','integer','exists:personnels,id'],
            'date_demande'         => ['nullable','date'],

            'valide_par'           => ['nullable','integer','exists:personnels,id'],
            'date_validation'      => ['nullable','date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id' => 'patient',
            'service_slug' => 'service',
            'code_examen' => 'code de l’examen',
            'nom_examen' => 'nom de l’examen',
            'prelevement' => 'prélèvement',
            'valeur_resultat' => 'valeur du résultat',
            'intervalle_reference' => 'intervalle de référence',
            'resultat_json' => 'résultat (JSON)',
            'demande_par' => 'demandeur',
            'valide_par' => 'validateur',
        ];
    }
}
