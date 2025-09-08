<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PansementStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        // alias FR -> colonnes
        $map = [
            'statut'    => 'status',
            'etat'      => 'etat_plaque',
            'produits'  => 'produits_utilises',
        ];
        $merged = [];
        foreach ($map as $fr => $en) {
            if ($this->has($fr)) $merged[$en] = $this->input($fr);
        }
        $this->merge($merged);
    }

    public function rules(): array
    {
        return [
            'patient_id'        => ['required','uuid','exists:patients,id'],
            'visite_id'         => ['nullable','uuid','exists:visites,id'],

            'type'              => ['required','string','max:100'],
            'date_soin'         => ['nullable','date'],
            'observation'       => ['nullable','string','max:1000'],
            'etat_plaque'       => ['nullable','string','max:150'],
            'produits_utilises' => ['nullable','string','max:1000'],

            // soignant auto (utilisateur connecté) — mais on accepte si fourni
            'soignant_id'       => ['nullable','uuid','exists:users,id'],

            'status'            => ['nullable','in:planifie,en_cours,clos,annule'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Le type de pansement est obligatoire.',
        ];
    }
}
