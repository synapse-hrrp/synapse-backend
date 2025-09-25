<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PansementStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Aliases FR -> colonnes réelles
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
            // Clés de rattachement
            'patient_id'        => ['required','uuid','exists:patients,id'],
            'visite_id'         => ['nullable','uuid','exists:visites,id'],

            // Données métier
            'type'              => ['required','string','max:100'],
            'date_soin'         => ['nullable','date'],
            'observation'       => ['nullable','string','max:1000'],
            'etat_plaque'       => ['nullable','string','max:150'],
            'produits_utilises' => ['nullable','string','max:1000'],

            // ⚠️ Déduit de la visite (visites.medecin_id => personnels.id). On interdit l’envoi côté client.
            'soignant_id'       => ['prohibited'],

            // Statut contrôlé
            'status'            => ['nullable','in:planifie,en_cours,clos,annule'],
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
            'type.required' => 'Le type de pansement est obligatoire.',
            'soignant_id.prohibited' => "Le soignant est défini automatiquement depuis la visite.",
        ];
    }
}
