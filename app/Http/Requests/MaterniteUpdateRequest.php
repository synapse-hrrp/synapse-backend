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
            'visite_id'  => ['sometimes','uuid','exists:visites,id'],

            'date_acte'  => ['sometimes','date'],
            'motif'      => ['sometimes','string','max:190'],
            'diagnostic' => ['sometimes','string','max:190'],

            'terme_grossesse'        => ['sometimes','string','max:50'],
            'age_gestationnel'       => ['sometimes','string','max:50'],
            'mouvements_foetaux'     => ['sometimes','boolean'],
            'tension_arterielle'     => ['sometimes','string','max:20'],
            'temperature'            => ['sometimes','numeric','min:30','max:45'],
            'frequence_cardiaque'    => ['sometimes','integer','min:0','max:300'],
            'frequence_respiratoire' => ['sometimes','integer','min:0','max:120'],
            'hauteur_uterine'        => ['sometimes','string','max:50'],
            'presentation'           => ['sometimes','string','max:50'],
            'battements_cardiaques_foetaux' => ['sometimes','string','max:50'],
            'col_uterin'             => ['sometimes','string','max:50'],
            'pertes'                 => ['sometimes','string','max:190'],

            'examen_clinique'        => ['sometimes','string'],
            'traitements'            => ['sometimes','string'],
            'observation'            => ['sometimes','string','max:1000'],

            'statut' => ['sometimes','in:en_cours,clos,annule'],

            'soignant_id' => ['prohibited'], // 🔒
        ];
    }
}
