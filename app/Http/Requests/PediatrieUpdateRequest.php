<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PediatrieUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'             => ['sometimes','uuid','exists:patients,id'],
            'visite_id'              => ['sometimes','uuid','exists:visites,id'],
            'date_acte'              => ['sometimes','date'],
            'motif'                  => ['sometimes','string','max:190'],
            'diagnostic'             => ['sometimes','string','max:190'],

            'poids'                  => ['sometimes','numeric','min:0','max:200'],
            'taille'                 => ['sometimes','numeric','min:0','max:250'],
            'temperature'            => ['sometimes','numeric','min:25','max:45'],
            'perimetre_cranien'      => ['sometimes','numeric','min:20','max:70'],
            'saturation'             => ['sometimes','integer','min:0','max:100'],
            'frequence_cardiaque'    => ['sometimes','integer','min:0','max:300'],
            'frequence_respiratoire' => ['sometimes','integer','min:0','max:120'],

            'examen_clinique'        => ['sometimes','string'],
            'traitements'            => ['sometimes','string'],
            'observation'            => ['sometimes','string','max:1000'],

            'statut'                 => ['sometimes','in:en_cours,clos,annule'],
        ];
    }
}
