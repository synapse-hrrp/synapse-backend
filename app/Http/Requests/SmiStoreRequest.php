<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SmiStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id'             => ['required','uuid','exists:patients,id'],
            'visite_id'              => ['nullable','uuid','exists:visites,id'],

            'date_acte'              => ['nullable','date'],
            'motif'                  => ['nullable','string','max:190'],
            'diagnostic'             => ['nullable','string','max:190'],

            'examen_clinique'        => ['nullable','string'],
            'traitements'            => ['nullable','string'],
            'observation'            => ['nullable','string','max:1000'],

            'tension_arterielle'     => ['nullable','string','max:20'],
            'temperature'            => ['nullable','numeric','min:30','max:45'],
            'frequence_cardiaque'    => ['nullable','integer','min:0','max:300'],
            'frequence_respiratoire' => ['nullable','integer','min:0','max:120'],

            'statut'                 => ['nullable','in:en_cours,clos,annule'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Le patient est obligatoire.',
            'motif.max'           => 'Le motif ne peut pas dépasser 190 caractères.',
            'diagnostic.max'      => 'Le diagnostic ne peut pas dépasser 190 caractères.',
            'temperature.min'     => 'La température doit être supérieure ou égale à 30°C.',
            'temperature.max'     => 'La température doit être inférieure ou égale à 45°C.',
        ];
    }
}
