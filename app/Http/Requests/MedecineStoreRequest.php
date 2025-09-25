<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedecineStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Le contrôleur gère déjà les permissions par token.
        return true;
    }

    public function rules(): array
    {
        return [
            // Identifiants de rattachement (UUID)
            'patient_id'  => ['required','uuid','exists:patients,id'],
            'visite_id'   => ['nullable','uuid','exists:visites,id'],

            // ⚠️ Interdit: sera toujours imposé depuis visites.medecin_id (Personnel)
            'soignant_id' => ['prohibited'],

            // Données médicales
            'date_acte'        => ['nullable','date'], // le modèle met un défaut si absent
            'motif'            => ['nullable','string','max:1000'],
            'diagnostic'       => ['nullable','string','max:2000'],
            'examen_clinique'  => ['nullable','string','max:5000'],
            'traitements'      => ['nullable','string','max:5000'],
            'observation'      => ['nullable','string','max:5000'],

            // Statut: adapte la liste à ton domaine si besoin
            'statut'           => ['nullable','string','in:en_cours,observation,hospitalisation,sortie,transfert,brouillon,termine,annule'],
        ];
    }

    public function messages(): array
    {
        return [
            'soignant_id.prohibited' => "Le champ soignant_id ne peut pas être envoyé : il est défini automatiquement depuis le médecin (Personnel) de la visite.",
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id'      => 'patient',
            'visite_id'       => 'visite',
            'soignant_id'     => 'soignant',
            'date_acte'       => "date de l’acte",
            'motif'           => 'motif',
            'diagnostic'      => 'diagnostic',
            'examen_clinique' => 'examen clinique',
            'traitements'     => 'traitements',
            'observation'     => 'observation',
            'statut'          => 'statut',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ne jamais laisser passer soignant_id depuis le client
        $input = $this->all();
        unset($input['soignant_id']);

        // Normaliser les chaînes vides en null
        foreach ([
            'visite_id','date_acte','motif','diagnostic','examen_clinique',
            'traitements','observation','statut'
        ] as $k) {
            if ($this->has($k) && $this->input($k) === '') {
                $input[$k] = null;
            }
        }

        $this->replace($input);
    }
}
