<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SmiUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'            => ['sometimes','uuid','exists:patients,id'],
            'visite_id'             => ['sometimes','nullable','uuid','exists:visites,id'],
            'date_acte'             => ['sometimes','nullable','date'],
            'motif'                 => ['sometimes','nullable','string','max:190'],
            'diagnostic'            => ['sometimes','nullable','string','max:190'],
            'examen_clinique'       => ['sometimes','nullable','string'],
            'traitements'           => ['sometimes','nullable','string'],
            'observation'           => ['sometimes','nullable','string'],
            'tension_arterielle'    => ['sometimes','nullable','string','max:20'],
            'temperature'           => ['sometimes','nullable','numeric','between:30,45'],
            'frequence_cardiaque'   => ['sometimes','nullable','integer','between:0,300'],
            'frequence_respiratoire'=> ['sometimes','nullable','integer','between:0,120'],
            'statut'                => ['sometimes','in:en_cours,clos,annule'],
        ];
    }
}
