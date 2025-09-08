<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AruUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'            => ['sometimes','uuid','exists:patients,id'],
            'visite_id'             => ['sometimes','nullable','uuid','exists:visites,id'],

            'date_acte'             => ['sometimes','nullable','date'],
            'motif'                 => ['sometimes','nullable','string','max:190'],
            'triage_niveau'         => ['sometimes','nullable','in:1,2,3,4,5'],

            'tension_arterielle'    => ['sometimes','nullable','string','max:20'],
            'temperature'           => ['sometimes','nullable','numeric','between:30,45'],
            'frequence_cardiaque'   => ['sometimes','nullable','integer','between:0,300'],
            'frequence_respiratoire'=> ['sometimes','nullable','integer','between:0,120'],
            'saturation'            => ['sometimes','nullable','integer','between:0,100'],
            'douleur_echelle'       => ['sometimes','nullable','integer','between:0,10'],
            'glasgow'               => ['sometimes','nullable','integer','between:3,15'],

            'examens_complementaires'=> ['sometimes','nullable','string'],
            'traitements'           => ['sometimes','nullable','string'],
            'observation'           => ['sometimes','nullable','string'],

            'statut'                => ['sometimes','nullable','in:en_cours,clos,annule'],
        ];
    }
}
