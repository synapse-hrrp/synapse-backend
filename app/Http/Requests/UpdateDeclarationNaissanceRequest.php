<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeclarationNaissanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mere_id'        => ['sometimes','uuid','exists:patients,id'],
            'service_slug'   => ['sometimes','nullable','string','exists:services,slug'],
            'accouchement_id'=> ['sometimes','nullable','uuid'],

            'created_via'    => ['sometimes','nullable','in:service,med,admin'],

            'bebe_nom'       => ['sometimes','string','max:120'],
            'bebe_prenom'    => ['sometimes','nullable','string','max:120'],
            'pere_nom'       => ['sometimes','nullable','string','max:120'],
            'pere_prenom'    => ['sometimes','nullable','string','max:120'],

            'date_heure_naissance' => ['sometimes','nullable','date'],
            'lieu_naissance'       => ['sometimes','nullable','string','max:150'],
            'sexe'                 => ['sometimes','nullable','in:M,F,I'],
            'poids_kg'             => ['sometimes','nullable','numeric','between:0,20'],
            'taille_cm'            => ['sometimes','nullable','numeric','between:0,80'],
            'apgar_1'              => ['sometimes','nullable','integer','between:0,10'],
            'apgar_5'              => ['sometimes','nullable','integer','between:0,10'],

            'numero_acte'          => ['sometimes','nullable','string','max:120'],
            'officier_etat_civil'  => ['sometimes','nullable','string','max:150'],
            'documents_json'       => ['sometimes','nullable','array'],

            'statut'               => ['sometimes','nullable','in:brouillon,valide,transmis'],
            'date_transmission'    => ['sometimes','nullable','date'],

            'prix'                 => ['prohibited'],
            'devise'               => ['prohibited'],
            'facture_id'           => ['prohibited'],
            'created_by_user_id'   => ['prohibited'],
        ];
    }
}
