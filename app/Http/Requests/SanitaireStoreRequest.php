<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SanitaireStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation
     */
    public function rules(): array
    {
        return [
            // Un des deux doit être présent (voir withValidator plus bas)
            'patient_id'    => ['nullable','uuid','exists:patients,id'],
            'visite_id'     => ['nullable','uuid','exists:visites,id'],

            'date_acte'     => ['nullable','date'],
            'date_debut'    => ['nullable','date'],
            'date_fin'      => ['nullable','date'],

            'type_action'   => ['nullable','in:nettoyage,desinfection,sterilisation,collecte_dechets,maintenance_hygiene'],
            'zone'          => ['nullable','string','max:150'],
            'sous_zone'     => ['nullable','string','max:150'],
            'niveau_risque' => ['nullable','in:faible,moyen,eleve'],

            'produits_utilises' => ['nullable','string'],
            'equipe'            => ['nullable','array'],
            'equipe.*'          => ['nullable','string','max:190'],

            'duree_minutes' => ['nullable','integer','min:0','max:6000'],
            'cout'          => ['nullable','numeric','min:0'],

            'observation'   => ['nullable','string'],
            'statut'        => ['nullable','in:planifie,en_cours,fait,annule'],
        ];
    }

    /**
     * Contrainte métier : au moins patient_id OU visite_id
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $hasPatient = (string) $this->input('patient_id', '') !== '';
            $hasVisite  = (string) $this->input('visite_id', '')  !== '';
            if (! $hasPatient && ! $hasVisite) {
                $v->errors()->add('patient_id', 'Fournissez au moins patient_id ou visite_id.');
                $v->errors()->add('visite_id',  'Fournissez au moins patient_id ou visite_id.');
            }
        });
    }

    /**
     * Libellés pour messages
     */
    public function attributes(): array
    {
        return [
            'patient_id'     => 'patient',
            'visite_id'      => 'visite',
            'date_acte'      => "date de l'acte",
            'date_debut'     => 'date de début',
            'date_fin'       => 'date de fin',
            'type_action'    => "type d'action",
            'niveau_risque'  => 'niveau de risque',
            'produits_utilises' => 'produits utilisés',
            'equipe'         => 'équipe',
            'duree_minutes'  => 'durée (minutes)',
            'cout'           => 'coût',
            'observation'    => 'observation',
            'statut'         => 'statut',
        ];
    }

    /**
     * Messages personnalisés (facultatif)
     */
    public function messages(): array
    {
        return [
            'type_action.in'    => "Le type d'action est invalide.",
            'niveau_risque.in'  => 'Le niveau de risque doit être faible, moyen ou eleve.',
            'statut.in'         => 'Le statut doit être planifie, en_cours, fait ou annule.',
        ];
    }
}
