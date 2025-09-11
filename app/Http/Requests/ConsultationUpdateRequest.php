<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultationUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'        => ['sometimes','uuid','exists:patients,id'],
            'visite_id'         => ['sometimes','nullable','uuid','exists:visites,id'],

            'categorie'         => ['sometimes','nullable','string','max:50'],
            'type_consultation' => ['sometimes','nullable','string','max:50'],

            'date_acte'         => ['sometimes','nullable','date'],
            'motif'             => ['sometimes','nullable','string','max:190'],
            'examen_clinique'   => ['sometimes','nullable','string'],
            'diagnostic'        => ['sometimes','nullable','string','max:190'],
            'prescriptions'     => ['sometimes','nullable','string'],
            'orientation_service'=>['sometimes','nullable','string'],

            'donnees_specifiques' => ['sometimes','nullable','array'],

            'statut'            => ['sometimes','in:en_cours,clos,annule'],
            'medecin_id'         => ['sometimes','nullable','integer','exists:users,id'],
        ];
    }
}
