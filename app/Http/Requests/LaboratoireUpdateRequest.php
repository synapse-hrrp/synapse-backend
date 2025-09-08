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
        $map = [
            'statut'    => 'status',
            'etat'      => 'etat_plaque',
            'produits'  => 'produits_utilises'
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
            // tout est nullable en update (PATCH)
            'patient_id'        => ['sometimes','uuid','exists:patients,id'],
            'visite_id'         => ['sometimes','nullable','uuid','exists:visites,id'],

            'type'              => ['sometimes','string','max:100'],

            'date_soin'         => ['sometimes','nullable','date'],
            'observation'       => ['sometimes','nullable','string','max:1000'],
            'etat_plaque'       => ['sometimes','nullable','string','max:150'],
            'produits_utilises' => ['sometimes','nullable','string','max:1000'],

            'soignant_id'       => ['sometimes','nullable','uuid','exists:users,id'],
            'status'            => ['sometimes','nullable','string','in:planifie,en_cours,clos,annule'],
        ];
    }
}
