<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaterniteUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id' => ['sometimes','uuid','exists:patients,id'],
            'visite_id'  => ['sometimes','nullable','uuid','exists:visites,id'],

            'date_acte'  => ['sometimes','nullable','date'],

            'motif'      => ['sometimes','nullable','string','max:190'],
            'diagnostic' => ['sometimes','nullable','string','max:190'],

            'terme_grossesse'    => ['sometimes','nullable','string','max:50'],
            'age_gestationnel'   => ['sometimes','nullable','integer','min:0','max:45'],
            'mouvements_foetaux' => ['sometimes','nullable','boolean'],

            'tension_arterielle'     => ['sometimes','nullable','string','max:20'],
            'temperature'            => ['sometimes','nullable','numeric','between:30,45'],
            'frequence_cardiaque'    => ['sometimes','nullable','integer','min:0','max:250'],
            'frequence_respiratoire' => ['sometimes','nullable','integer','min:0','max:80'],

            'hauteur_uterine'              => ['sometimes','nullable','numeric','between:0,60'],
            'presentation'                 => ['sometimes','nullable','string','max:50'],
            'battements_cardiaques_foetaux'=> ['sometimes','nullable','integer','min:0','max:240'],
            'col_uterin'                   => ['sometimes','nullable','string','max:100'],
            'pertes'                       => ['sometimes','nullable','string','max:100'],

            'examen_clinique' => ['sometimes','nullable','string'],
            'traitements'     => ['sometimes','nullable','string'],
            'observation'     => ['sometimes','nullable','string'],

            'statut' => ['sometimes','nullable','in:en_cours,clos,annule'],
        ];
    }
}
