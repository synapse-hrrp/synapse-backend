<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaterniteStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id' => ['required','uuid','exists:patients,id'],
            'visite_id'  => ['nullable','uuid','exists:visites,id'],

            'date_acte'  => ['nullable','date'],

            'motif'      => ['nullable','string','max:190'],
            'diagnostic' => ['nullable','string','max:190'],

            'terme_grossesse'    => ['nullable','string','max:50'],
            'age_gestationnel'   => ['nullable','integer','min:0','max:45'],
            'mouvements_foetaux' => ['nullable','boolean'],

            'tension_arterielle'     => ['nullable','string','max:20'],
            'temperature'            => ['nullable','numeric','between:30,45'],
            'frequence_cardiaque'    => ['nullable','integer','min:0','max:250'],
            'frequence_respiratoire' => ['nullable','integer','min:0','max:80'],

            'hauteur_uterine'             => ['nullable','numeric','between:0,60'],
            'presentation'                => ['nullable','string','max:50'],
            'battements_cardiaques_foetaux'=> ['nullable','integer','min:0','max:240'],
            'col_uterin'                  => ['nullable','string','max:100'],
            'pertes'                      => ['nullable','string','max:100'],

            'examen_clinique' => ['nullable','string'],
            'traitements'     => ['nullable','string'],
            'observation'     => ['nullable','string'],

            'statut' => ['nullable','in:en_cours,clos,annule'],
        ];
    }
}
