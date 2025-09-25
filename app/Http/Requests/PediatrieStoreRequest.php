<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PediatrieStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id'             => ['required','uuid','exists:patients,id'],
            'visite_id'              => ['nullable','uuid','exists:visites,id'],

            // ⚠️ soignant_id jamais fourni par le client (lié à visite.medecin_id)
            'soignant_id'            => ['prohibited'],

            'date_acte'              => ['nullable','date'],
            'motif'                  => ['required','string','max:190'],
            'diagnostic'             => ['nullable','string','max:190'],

            'poids'                  => ['nullable','numeric','min:0','max:200'],
            'taille'                 => ['nullable','numeric','min:0','max:250'],
            'temperature'            => ['nullable','numeric','min:25','max:45'],
            'perimetre_cranien'      => ['nullable','numeric','min:20','max:70'],
            'saturation'             => ['nullable','integer','min:0','max:100'],
            'frequence_cardiaque'    => ['nullable','integer','min:0','max:300'],
            'frequence_respiratoire' => ['nullable','integer','min:0','max:120'],

            'examen_clinique'        => ['nullable','string'],
            'traitements'            => ['nullable','string'],
            'observation'            => ['nullable','string','max:1000'],

            'statut'                 => ['nullable','in:en_cours,clos,annule'],
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id'             => 'patient',
            'visite_id'              => 'visite',
            'soignant_id'            => 'soignant (automatique, ne pas envoyer)',
            'date_acte'              => 'date de l’acte',
            'motif'                  => 'motif',
            'diagnostic'             => 'diagnostic',
            'poids'                  => 'poids',
            'taille'                 => 'taille',
            'temperature'            => 'température',
            'perimetre_cranien'      => 'périmètre crânien',
            'saturation'             => 'saturation',
            'frequence_cardiaque'    => 'fréquence cardiaque',
            'frequence_respiratoire' => 'fréquence respiratoire',
            'examen_clinique'        => 'examen clinique',
            'traitements'            => 'traitements',
            'observation'            => 'observation',
            'statut'                 => 'statut',
        ];
    }
}
