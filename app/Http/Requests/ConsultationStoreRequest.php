<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultationStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'        => ['required','uuid','exists:patients,id'],
            'visite_id'         => ['nullable','uuid','exists:visites,id'],

            'categorie'         => ['nullable','string','max:50'],
            'type_consultation' => ['nullable','string','max:50'],

            'date_acte'         => ['nullable','date'],
            'motif'             => ['nullable','string','max:190'],
            'examen_clinique'   => ['nullable','string'],
            'diagnostic'        => ['nullable','string','max:190'],
            'prescriptions'     => ['nullable','string'],
            'orientation_service'=>['nullable','string'],

            'donnees_specifiques' => ['nullable','array'],

            'statut'            => ['nullable','in:en_cours,clos,annule'],
            'medecin_id'         => ['nullable','integer','exists:users,id'],
        ];
    }
}
