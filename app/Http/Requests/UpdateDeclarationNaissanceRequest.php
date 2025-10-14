<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeclarationNaissanceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'           => ['sometimes','exists:patients,id'],
            'mere_id'              => ['sometimes','exists:patients,id'],
            'pere_id'              => ['sometimes','nullable','exists:patients,id'],
            'service_slug'         => ['sometimes','nullable','string','exists:services,slug'],
            'accouchement_id'      => ['sometimes','nullable','exists:accouchements,id'],

            'date_heure_naissance' => ['sometimes','nullable','date'],
            'lieu_naissance'       => ['sometimes','nullable','string','max:255'],
            'sexe'                 => ['sometimes','nullable','in:M,F,I'],
            'poids_kg'             => ['sometimes','nullable','numeric','min:0','max:20'],
            'taille_cm'            => ['sometimes','nullable','numeric','min:0','max:80'],
            'apgar_1'              => ['sometimes','nullable','integer','min:0','max:10'],
            'apgar_5'              => ['sometimes','nullable','integer','min:0','max:10'],

            'numero_acte'          => ['sometimes','nullable','string','max:255'],
            'officier_etat_civil'  => ['sometimes','nullable','string','max:255'],
            'documents_json'       => ['sometimes','nullable','array'],

            'statut'               => ['sometimes','in:brouillon,valide,transmis'],
            'date_transmission'    => ['sometimes','nullable','date'],
        ];
    }
}
