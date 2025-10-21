<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeclarationNaissanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mere_id'        => ['required','uuid','exists:patients,id'],
            'service_slug'   => ['nullable','string','exists:services,slug'],
            'accouchement_id'=> ['nullable','uuid'], // si table: ajoute exists:accouchements,id

            'created_via'    => ['nullable','in:service,med,admin'],

            // identités (saisie manuelle)
            'bebe_nom'       => ['required','string','max:120'],
            'bebe_prenom'    => ['nullable','string','max:120'],
            'pere_nom'       => ['nullable','string','max:120'],
            'pere_prenom'    => ['nullable','string','max:120'],

            // naissance
            'date_heure_naissance' => ['nullable','date'],
            'lieu_naissance'       => ['nullable','string','max:150'],
            'sexe'                 => ['nullable','in:M,F,I'],
            'poids_kg'             => ['nullable','numeric','between:0,20'],
            'taille_cm'            => ['nullable','numeric','between:0,80'],
            'apgar_1'              => ['nullable','integer','between:0,10'],
            'apgar_5'              => ['nullable','integer','between:0,10'],

            // état civil
            'numero_acte'          => ['nullable','string','max:120'],
            'officier_etat_civil'  => ['nullable','string','max:150'],
            'documents_json'       => ['nullable','array'],

            // workflow
            'statut'               => ['nullable','in:brouillon,valide,transmis'],
            'date_transmission'    => ['nullable','date'],

            // interdits
            'prix'                 => ['prohibited'],
            'devise'               => ['prohibited'],
            'facture_id'           => ['prohibited'],
            'created_by_user_id'   => ['prohibited'],
        ];
    }
}
