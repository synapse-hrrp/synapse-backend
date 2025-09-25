<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // IDs en UUID
            'patient_id'          => ['required', 'uuid', 'exists:patients,id'],
            'visite_id'           => ['nullable', 'uuid', 'exists:visites,id'],

            // Typage
            'categorie'           => ['nullable', 'string', 'max:50'],
            'type_consultation'   => ['nullable', 'string', 'max:50'],

            // Données cliniques
            'date_acte'           => ['nullable', 'date'],
            'motif'               => ['nullable', 'string', 'max:190'],
            'examen_clinique'     => ['nullable', 'string'],
            'diagnostic'          => ['nullable', 'string', 'max:190'],
            'prescriptions'       => ['nullable', 'string'],
            'orientation_service' => ['nullable', 'string', 'max:150'],

            // Données spécifiques (tableau)
            'donnees_specifiques' => ['nullable', 'array'],

            // Statut contrôlé
            'statut'              => ['nullable', 'in:en_cours,clos,annule'],

            // Le médecin est un Personnel (pas users)
            'medecin_id'          => ['nullable', 'uuid', 'exists:personnels,id'],
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
