<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeclarationNaissanceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'           => ['required','exists:patients,id'],
            'mere_id'              => ['required','exists:patients,id'],
            'pere_id'              => ['nullable','exists:patients,id'],
            'service_slug'         => ['nullable','string','exists:services,slug'],
            'accouchement_id'      => ['nullable','exists:accouchements,id'],

            'date_heure_naissance' => ['nullable','date'],
            'lieu_naissance'       => ['nullable','string','max:255'],
            'sexe'                 => ['nullable','in:M,F,I'],
            'poids_kg'             => ['nullable','numeric','min:0','max:20'],
            'taille_cm'            => ['nullable','numeric','min:0','max:80'],
            'apgar_1'              => ['nullable','integer','min:0','max:10'],
            'apgar_5'              => ['nullable','integer','min:0','max:10'],

            'numero_acte'          => ['nullable','string','max:255'],
            'officier_etat_civil'  => ['nullable','string','max:255'],
            'documents_json'       => ['nullable','array'],

            'statut'               => ['nullable','in:brouillon,valide,transmis'],
            'date_transmission'    => ['nullable','date'],
        ];
    }
}
