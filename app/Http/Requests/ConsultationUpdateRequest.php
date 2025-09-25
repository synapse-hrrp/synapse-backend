<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultationUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'          => ['sometimes','uuid','exists:patients,id'],
            'visite_id'           => ['sometimes','nullable','uuid','exists:visites,id'],

            'categorie'           => ['sometimes','nullable','string','max:50'],
            'type_consultation'   => ['sometimes','nullable','string','max:50'],

            'date_acte'           => ['sometimes','nullable','date'],
            'motif'               => ['sometimes','nullable','string','max:190'],
            'examen_clinique'     => ['sometimes','nullable','string'],
            'diagnostic'          => ['sometimes','nullable','string','max:190'],
            'prescriptions'       => ['sometimes','nullable','string'],
            'orientation_service' => ['sometimes','nullable','string','max:150'],

            'donnees_specifiques' => ['sometimes','nullable','array'],

            'statut'              => ['sometimes','in:en_cours,clos,annule'],

            // ⚠️ médecin = personnels.id (UUID), pas users.id
            'medecin_id'          => ['sometimes','nullable','uuid','exists:personnels,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id'          => 'patient',
            'visite_id'           => 'visite',
            'date_acte'           => 'date de l’acte',
            'type_consultation'   => 'type de consultation',
            'examen_clinique'     => 'examen clinique',
            'prescriptions'       => 'prescriptions',
            'orientation_service' => 'orientation de service',
            'donnees_specifiques' => 'données spécifiques',
            'statut'              => 'statut',
            'medecin_id'          => 'médecin',
        ];
    }
}
