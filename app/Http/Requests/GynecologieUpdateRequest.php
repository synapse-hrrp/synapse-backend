<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GynecologieUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['sometimes','uuid','exists:patients,id'],
            'visite_id'  => ['sometimes','uuid','exists:visites,id'],
            'date_acte'  => ['sometimes','date'],

            'motif'             => ['sometimes','string','max:190'],
            'diagnostic'        => ['sometimes','string','max:190'],
            'examen_clinique'   => ['sometimes','string'],
            'traitements'       => ['sometimes','string'],
            'observation'       => ['sometimes','string'],

            'tension_arterielle'     => ['sometimes','string','max:20'],
            'temperature'            => ['sometimes','numeric','min:30','max:45'],
            'frequence_cardiaque'    => ['sometimes','integer','min:0','max:300'],
            'frequence_respiratoire' => ['sometimes','integer','min:0','max:120'],

            'statut' => ['sometimes','in:en_cours,clos,annule'],

            // 🔒 interdit d’envoyer soignant_id : toujours déduit de la visite
            'soignant_id' => ['prohibited'],
        ];
    }
}
