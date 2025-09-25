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

            'terme_grossesse'        => ['nullable','string','max:50'],
            'age_gestationnel'       => ['nullable','string','max:50'],
            'mouvements_foetaux'     => ['nullable','boolean'],
            'tension_arterielle'     => ['nullable','string','max:20'],
            'temperature'            => ['nullable','numeric','min:30','max:45'],
            'frequence_cardiaque'    => ['nullable','integer','min:0','max:300'],
            'frequence_respiratoire' => ['nullable','integer','min:0','max:120'],
            'hauteur_uterine'        => ['nullable','string','max:50'],
            'presentation'           => ['nullable','string','max:50'],
            'battements_cardiaques_foetaux' => ['nullable','string','max:50'],
            'col_uterin'             => ['nullable','string','max:50'],
            'pertes'                 => ['nullable','string','max:190'],

            'examen_clinique'        => ['nullable','string'],
            'traitements'            => ['nullable','string'],
            'observation'            => ['nullable','string','max:1000'],

            'statut' => ['nullable','in:en_cours,clos,annule'],

            'soignant_id' => ['prohibited'], // 🔒
        ];
    }
}
