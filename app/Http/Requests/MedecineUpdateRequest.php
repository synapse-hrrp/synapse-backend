<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedecineUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Le contrôleur gère déjà les permissions par token.
        return true;
    }

    public function rules(): array
    {
        return [
            // Identifiants (UUID) — adapte en 'integer' si besoin
            'patient_id'  => ['sometimes','uuid','exists:patients,id'],
            'visite_id'   => ['sometimes','nullable','uuid','exists:visites,id'],

            // ⚠️ Interdit: toujours imposé depuis visites.medecin_id (Personnel)
            'soignant_id' => ['prohibited'],

            // Données médicales
            'date_acte'        => ['sometimes','nullable','date'],
            'motif'            => ['sometimes','nullable','string','max:1000'],
            'diagnostic'       => ['sometimes','nullable','string','max:2000'],
            'examen_clinique'  => ['sometimes','nullable','string','max:5000'],
            'traitements'      => ['sometimes','nullable','string','max:5000'],
            'observation'      => ['sometimes','nullable','string','max:5000'],
            'tension_arterielle'    => ['sometimes','nullable','string','max:20'],
            'temperature'           => ['sometimes','nullable','numeric','between:25,45'],
            'frequence_cardiaque'   => ['sometimes','nullable','integer','between:0,300'],
            'frequence_respiratoire'=> ['sometimes','nullable','integer','between:0,120'],

            // Statut (même liste que Store + valeurs métier possibles)
            'statut'           => ['sometimes','nullable','string','in:en_cours,observation,hospitalisation,sortie,transfert,brouillon,termine,annule'],
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
            'tension_arterielle' => 'tension artérielle',
            'temperature'        => 'température',
            'frequence_cardiaque' => 'fréquence cardiaque',
            'frequence_respiratoire' => 'fréquence respiratoire',
            'statut'          => 'statut',
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        // Ne jamais accepter soignant_id depuis le client
        unset($input['soignant_id']);

        // Normaliser les chaînes vides en null
        foreach ([
            'visite_id','date_acte','motif','diagnostic','examen_clinique',
            'traitements','observation','statut','tension_arterielle',
            'temperature','frequence_cardiaque','frequence_respiratoire'
        ] as $k) {
            if ($this->has($k) && $this->input($k) === '') {
                $input[$k] = null;
            }
        }

        $this->replace($input);
    }
}
