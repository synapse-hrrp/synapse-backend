<?php
// app/Http/Requests/PatientStoreRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatientStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // AAA-YYYY-NNNNNN, insensible à la casse
            'numero_dossier' => [
                'nullable','string','max:32',
                'regex:/^[A-Z]{3}-\d{4}-\d{6}$/i',
                'unique:patients,numero_dossier'
            ],

            'nom' => ['required','string','min:1','max:100'],
            'prenom' => ['required','string','min:1','max:100'],

            'date_naissance' => ['nullable','date','before:today'],
            'lieu_naissance' => ['nullable','string','max:150'],
            'age_reporte' => ['nullable','integer','min:0','max:140'],
            'sexe' => ['nullable','in:M,F,X'],

            'nationalite' => ['nullable','string','max:80'],
            'profession' => ['nullable','string','max:120'],
            'adresse' => ['nullable','string'],
            'quartier' => ['nullable','string','max:120'],
            'telephone' => ['nullable','string','min:6','max:30'],
            'statut_matrimonial' => ['nullable','string','max:40'],

            'proche_nom' => ['nullable','string','max:150'],
            'proche_tel' => ['nullable','string','max:30'],

            'groupe_sanguin' => ['nullable','in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'allergies' => ['nullable','string'],

            // Temporaire : plus de 'uuid' ni 'exists' pour éviter les 422/1146
            'assurance_id' => ['nullable','string','max:64'],
            'numero_assure' => ['nullable','string','max:64'],

            'is_active' => ['sometimes','boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_dossier.regex'  => "Le numéro de dossier doit suivre le format AAA-YYYY-NNNNNN (ex. PAT-2025-000001).",
            'numero_dossier.unique' => "Ce numéro de dossier est déjà utilisé.",

            'date_naissance.before' => "La date de naissance doit être antérieure à aujourd'hui.",
            'sexe.in'               => "Le sexe doit être l'une des valeurs: M, F ou X.",
        ];
    }
}
