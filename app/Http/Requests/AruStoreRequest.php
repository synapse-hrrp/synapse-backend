<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AruStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Adapte si tu utilises des policies/abilities
        return true;
    }

    public function rules(): array
    {
        return [
            // Identifiants de rattachement
            'patient_id'  => ['required','uuid','exists:patients,id'],
            'visite_id'   => ['nullable','uuid','exists:visites,id'],
            'service_id'  => ['nullable','integer','exists:services,id'], // adapte si UUID

            // ⚠️ Interdit : toujours calculé depuis visite.medecin_id
            'soignant_id' => ['prohibited'],

            // Données médicales
            'date_acte'              => ['nullable','date'],
            'motif'                  => ['nullable','string','max:255'],
            'triage_niveau'          => ['nullable','string','max:50'], // ou ['nullable','integer','between:1,5']
            'tension_arterielle'     => ['nullable','string','max:20'],
            'temperature'            => ['nullable','numeric','between:25,45'],
            'frequence_cardiaque'    => ['nullable','integer','between:0,300'],
            'frequence_respiratoire' => ['nullable','integer','between:0,120'],
            'saturation'             => ['nullable','integer','between:0,100'],
            'douleur_echelle'        => ['nullable','integer','between:0,10'],
            'glasgow'                => ['nullable','integer','between:3,15'],
            'examens_complementaires'=> ['nullable','string'],
            'traitements'            => ['nullable','string'],
            'observation'            => ['nullable','string'],

            // Statut (tu peux adapter la liste)
            'statut'                 => ['nullable','string','in:en_cours,observation,hospitalisation,sortie,transfert'],
        ];
    }

    public function messages(): array
    {
        return [
            'soignant_id.prohibited' => "Le champ soignant_id ne peut pas être envoyé : il est défini automatiquement avec le médecin de la visite.",
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalisation légère : transforme les chaînes vides en null
        $input = $this->all();

        foreach ([
            'visite_id','service_id','date_acte','motif','triage_niveau',
            'tension_arterielle','temperature','examens_complementaires',
            'traitements','observation','statut'
        ] as $k) {
            if ($this->has($k) && $this->input($k) === '') {
                $input[$k] = null;
            }
        }

        // On s’assure de ne jamais laisser passer un soignant_id venant du client
        unset($input['soignant_id']);

        $this->replace($input);
    }
}
