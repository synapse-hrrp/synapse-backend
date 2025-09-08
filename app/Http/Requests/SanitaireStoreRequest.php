<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SanitaireStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'   => ['nullable','uuid','exists:patients,id'],
            'visite_id'    => ['nullable','uuid','exists:visites,id'],

            'date_acte'    => ['nullable','date'],
            'date_debut'   => ['nullable','date'],
            'date_fin'     => ['nullable','date'],

            'type_action'  => ['nullable','in:nettoyage,desinfection,sterilisation,collecte_dechets,maintenance_hygiene'],
            'zone'         => ['nullable','string','max:150'],
            'sous_zone'    => ['nullable','string','max:150'],
            'niveau_risque'=> ['nullable','in:faible,moyen,eleve'],

            'produits_utilises' => ['nullable','string'],
            'equipe'            => ['nullable','array'],
            'equipe.*'          => ['nullable','string','max:190'],
            'duree_minutes'     => ['nullable','integer','min:0','max:6000'],
            'cout'              => ['nullable','numeric','min:0'],

            'observation'  => ['nullable','string'],
            'statut'       => ['nullable','in:planifie,en_cours,fait,annule'],
        ];
    }
}
