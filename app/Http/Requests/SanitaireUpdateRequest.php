<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SanitaireUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'   => ['sometimes','nullable','uuid','exists:patients,id'],
            'visite_id'    => ['sometimes','nullable','uuid','exists:visites,id'],

            'date_acte'    => ['sometimes','nullable','date'],
            'date_debut'   => ['sometimes','nullable','date'],
            'date_fin'     => ['sometimes','nullable','date'],

            'type_action'  => ['sometimes','nullable','in:nettoyage,desinfection,sterilisation,collecte_dechets,maintenance_hygiene'],
            'zone'         => ['sometimes','nullable','string','max:150'],
            'sous_zone'    => ['sometimes','nullable','string','max:150'],
            'niveau_risque'=> ['sometimes','nullable','in:faible,moyen,eleve'],

            'produits_utilises' => ['sometimes','nullable','string'],
            'equipe'            => ['sometimes','nullable','array'],
            'equipe.*'          => ['sometimes','nullable','string','max:190'],
            'duree_minutes'     => ['sometimes','nullable','integer','min:0','max:6000'],
            'cout'              => ['sometimes','nullable','numeric','min:0'],

            'observation'  => ['sometimes','nullable','string'],
            'statut'       => ['sometimes','nullable','in:planifie,en_cours,fait,annule'],
        ];
    }
}
