<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SanitaireUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id'    => ['sometimes','uuid','exists:patients,id'],
            'visite_id'     => ['sometimes','uuid','exists:visites,id'],

            'date_acte'     => ['sometimes','date'],
            'date_debut'    => ['sometimes','date'],
            'date_fin'      => ['sometimes','date'],

            'type_action'   => ['sometimes','in:nettoyage,desinfection,sterilisation,collecte_dechets,maintenance_hygiene'],
            'zone'          => ['sometimes','string','max:150'],
            'sous_zone'     => ['sometimes','string','max:150'],
            'niveau_risque' => ['sometimes','in:faible,moyen,eleve'],

            'produits_utilises' => ['sometimes','string'],
            'equipe'            => ['sometimes','array'],
            'equipe.*'          => ['sometimes','string','max:190'],

            'duree_minutes' => ['sometimes','integer','min:0','max:6000'],
            'cout'          => ['sometimes','numeric','min:0'],

            'observation'   => ['sometimes','string'],
            'statut'        => ['sometimes','in:planifie,en_cours,fait,annule'],
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id'     => 'patient',
            'visite_id'      => 'visite',
            'date_acte'      => "date de l'acte",
            'date_debut'     => 'date de début',
            'date_fin'       => 'date de fin',
            'type_action'    => "type d'action",
            'zone'           => 'zone',
            'sous_zone'      => 'sous-zone',
            'niveau_risque'  => 'niveau de risque',
            'produits_utilises' => 'produits utilisés',
            'equipe'         => 'équipe',
            'duree_minutes'  => 'durée (minutes)',
            'cout'           => 'coût',
            'observation'    => 'observation',
            'statut'         => 'statut',
        ];
    }
}
