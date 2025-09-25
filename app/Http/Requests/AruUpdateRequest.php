<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AruUpdateRequest extends FormRequest
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
            'patient_id'  => ['sometimes','uuid','exists:patients,id'],
            'visite_id'   => ['sometimes','uuid','exists:visites,id'],
            'service_id'  => ['sometimes','integer','exists:services,id'], // adapte si UUID

            // ⚠️ Interdit : toujours calculé depuis visite.medecin_id
            'soignant_id' => ['prohibited'],

            // Données médicales
            'date_acte'              => ['sometimes','nullable','date'],
            'motif'                  => ['sometimes','nullable','string','max:255'],
            'triage_niveau'          => ['sometimes','nullable','string','max:50'], // ou integer 1..5
            'tension_arterielle'     => ['sometimes','nullable','string','max:20'],
            'temperature'            => ['sometimes','nullable','numeric','between:25,45'],
            'frequence_cardiaque'    => ['sometimes','nullable','integer','between:0,300'],
            'frequence_respiratoire' => ['sometimes','nullable','integer','between:0,120'],
            'saturation'             => ['sometimes','nullable','integer','between:0,100'],
            'douleur_echelle'        => ['sometimes','nullable','integer','between:0,10'],
            'glasgow'                => ['sometimes','nullable','integer','between:3,15'],
            'examens_complementaires'=> ['sometimes','nullable','string'],
            'traitements'            => ['sometimes','nullable','string'],
            'observation'            => ['sometimes','nullable','string'],

            // Statut (mêmes valeurs qu’en création)
            'statut'                 => ['sometimes','nullable','string','in:en_cours,observation,hospitalisation,sortie,transfert'],
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

        // Ne jamais accepter soignant_id depuis le client
        unset($input['soignant_id']);

        $this->replace($input);
    }
}
