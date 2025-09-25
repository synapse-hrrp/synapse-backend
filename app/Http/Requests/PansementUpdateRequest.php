<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PansementUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Aliases FR -> colonnes réelles (si l'API envoie encore ces clés)
        $map = [
            'statut'   => 'status',
            'etat'     => 'etat_plaque',
            'produits' => 'produits_utilises',
        ];

        $merged = [];
        foreach ($map as $fr => $col) {
            if ($this->filled($fr)) {
                $merged[$col] = $this->input($fr);
            }
        }

        $this->merge($merged);
    }

    public function rules(): array
    {
        return [
            // Rattachements (optionnels à l’update)
            'patient_id'        => ['sometimes','uuid','exists:patients,id'],
            'visite_id'         => ['sometimes','uuid','exists:visites,id'],

            // Données métier (toutes optionnelles à l’update)
            'type'              => ['sometimes','string','max:100'],
            'date_soin'         => ['sometimes','date'],
            'observation'       => ['sometimes','string','max:1000'],
            'etat_plaque'       => ['sometimes','string','max:150'],
            'produits_utilises' => ['sometimes','string','max:1000'],

            // ⚠️ non modifiable manuellement (déduit de la visite)
            'soignant_id'       => ['prohibited'],

            // Statut
            'status'            => ['sometimes','in:planifie,en_cours,clos,annule'],
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id'        => 'patient',
            'visite_id'         => 'visite',
            'type'              => 'type de pansement',
            'date_soin'         => 'date du soin',
            'observation'       => 'observation',
            'etat_plaque'       => 'état de la plaie',
            'produits_utilises' => 'produits utilisés',
            'status'            => 'statut',
        ];
    }

    public function messages(): array
    {
        return [
            'soignant_id.prohibited' => "Le soignant est défini automatiquement depuis la visite et ne peut pas être modifié.",
        ];
    }
}
