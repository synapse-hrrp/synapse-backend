<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccouchementRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'mere_id'                 => ['required','uuid','exists:patients,id'],
            'service_slug'            => ['nullable','string','exists:services,slug'],

            'date_heure_accouchement' => ['nullable','date'],
            'terme_gestationnel_sa'   => ['nullable','integer','min:10','max:45'],
            'voie'                    => ['nullable','string','max:20'],
            'presentation'            => ['nullable','string','max:30'],
            'type_cesarienne'         => ['nullable','string','max:50'],
            'score_apgar_1_5'         => ['nullable','string','max:10'],
            'poids_kg'                => ['nullable','numeric','min:0','max:10'],
            'taille_cm'               => ['nullable','numeric','min:0','max:80'],
            'sexe'                    => ['nullable','in:M,F,I'],
            'complications_json'      => ['nullable','array'],
            'notes'                   => ['nullable','string'],
            'statut'                  => ['nullable','in:brouillon,valide,clos'],

            'sage_femme_id'           => ['nullable','uuid','exists:personnels,id'],
            'obstetricien_id'         => ['nullable','uuid','exists:personnels,id'],

            // sécurité: on n’accepte pas prix/devise/facture_id côté client
        ];
    }
}
