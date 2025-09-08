<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AruStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'            => ['required','uuid','exists:patients,id'],
            'visite_id'             => ['nullable','uuid','exists:visites,id'],

            'date_acte'             => ['nullable','date'],
            'motif'                 => ['nullable','string','max:190'],
            'triage_niveau'         => ['nullable','in:1,2,3,4,5'],

            'tension_arterielle'    => ['nullable','string','max:20'],
            'temperature'           => ['nullable','numeric','between:30,45'],
            'frequence_cardiaque'   => ['nullable','integer','between:0,300'],
            'frequence_respiratoire'=> ['nullable','integer','between:0,120'],
            'saturation'            => ['nullable','integer','between:0,100'],
            'douleur_echelle'       => ['nullable','integer','between:0,10'],
            'glasgow'               => ['nullable','integer','between:3,15'],

            'examens_complementaires'=> ['nullable','string'],
            'traitements'           => ['nullable','string'],
            'observation'           => ['nullable','string'],

            'statut'                => ['nullable','in:en_cours,clos,annule'],
        ];
    }
}
