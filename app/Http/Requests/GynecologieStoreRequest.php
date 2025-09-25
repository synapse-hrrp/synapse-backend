<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GynecologieStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required','uuid','exists:patients,id'],
            'visite_id'  => ['nullable','uuid','exists:visites,id'],

            'date_acte'  => ['nullable','date'],

            'motif'             => ['nullable','string','max:190'],
            'diagnostic'        => ['nullable','string','max:190'],
            'examen_clinique'   => ['nullable','string'],
            'traitements'       => ['nullable','string'],
            'observation'       => ['nullable','string'],

            'tension_arterielle'     => ['nullable','string','max:20'],
            'temperature'            => ['nullable','numeric','min:30','max:45'],
            'frequence_cardiaque'    => ['nullable','integer','min:0','max:300'],
            'frequence_respiratoire' => ['nullable','integer','min:0','max:120'],

            'statut' => ['nullable','in:en_cours,clos,annule'],

            // 🔒 on interdit explicitement l’envoi du soignant
            'soignant_id' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id'            => 'patient',
            'visite_id'             => 'visite',
            'date_acte'             => 'date de l’acte',
            'motif'                 => 'motif',
            'diagnostic'            => 'diagnostic',
            'examen_clinique'       => 'examen clinique',
            'traitements'           => 'traitements',
            'observation'           => 'observation',
            'tension_arterielle'    => 'tension artérielle',
            'temperature'           => 'température',
            'frequence_cardiaque'   => 'fréquence cardiaque',
            'frequence_respiratoire'=> 'fréquence respiratoire',
            'statut'                => 'statut',
        ];
    }
}
