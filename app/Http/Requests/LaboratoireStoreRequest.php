<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PansementStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // tu gères l’auth via Sanctum + middleware
    }

    /**
     * Permettre des alias FR -> colonnes DB
     * Ex: { "date": "..."} deviendra "date_soin"
     */
    protected function prepareForValidation(): void
    {
        $map = [
            'date'              => 'date_soin',
            'prochain_soin'     => 'prochain_soin_at',
            'etat'              => 'etat_plaie',
            'soins'             => 'soins_effectues',
            'materiel'          => 'materiel_utilise',
            'douleur'           => 'douleur_score',
            'statut'            => 'statut',
        ];

        $merged = [];
        foreach ($map as $fr => $en) {
            if ($this->has($fr)) {
                $merged[$en] = $this->input($fr);
            }
        }
        $this->merge($merged);
    }

    public function rules(): array
    {
        return [
            'patient_id'        => ['required','uuid','exists:patients,id'],

            // ✅ Option A : "type" est obligatoire
            'type'              => ['required','string','in:simple,complexe,brulure,post-op'],

            'localisation'      => ['nullable','string','max:150'],
            'taille'            => ['nullable','string','max:50'],     // ex: "2x3 cm"
            'etat_plaie'        => ['nullable','string','max:150'],
            'soins_effectues'   => ['nullable','string','max:1000'],
            'materiel_utilise'  => ['nullable','string','max:500'],
            'douleur_score'     => ['nullable','integer','min:0','max:10'],
            'observation'       => ['nullable','string','max:1000'],

            'date_soin'         => ['nullable','date'],
            'prochain_soin_at'  => ['nullable','date'],

            'statut'            => ['nullable','string','in:planifie,en_cours,clos,annule'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Le type de pansement est obligatoire (simple, complexe, brulure, post-op).',
            'patient_id.exists' => 'Le patient spécifié est introuvable.',
        ];
    }
}
