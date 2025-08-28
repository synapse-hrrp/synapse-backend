<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;

class VisiteStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $hasTarifs = Schema::hasTable('tarifs'); // si la table n'existe pas encore, on interdit le champ

        return [
            // patients.id = UUID
            'patient_id' => ['required','uuid','exists:patients,id'],

            // services.id = BIGINT
            'service_id' => ['required','integer','exists:services,id'],

            'plaintes_motif'      => ['nullable','string'],
            'hypothese_diagnostic'=> ['nullable','string'],

            // users.id = BIGINT  (⚠️ pas UUID)
            'medecin_id' => ['nullable','integer','exists:users,id'],
            'medecin_nom'=> ['nullable','string','max:150'],

            // création d'une affectation en cascade côté contrôleur si true
            'create_affectation' => ['nullable','boolean'],

            // Pricing (optionnel)
            // si la table tarifs n'existe pas : ce champ est interdit pour éviter l'erreur 1146
            'tarif_id'     => $hasTarifs ? ['nullable','uuid','exists:tarifs,id'] : ['prohibited'],
            'remise_pct'   => ['nullable','numeric','min:0','max:100'],
            'exempt'       => ['nullable','boolean'],
            'motif_gratuite' => ['nullable','string','max:150'],
        ];
    }

    // (optionnel) messages personnalisés
    public function messages(): array
    {
        return [
            'patient_id.uuid'     => "Le patient_id doit être un UUID valide.",
            'patient_id.exists'   => "Le patient spécifié est introuvable.",
            'service_id.required' => "Le service est obligatoire.",
            'service_id.integer'  => "Le service doit être un identifiant numérique.",
            'service_id.exists'   => "Le service spécifié est introuvable.",
            'medecin_id.integer'  => "Le médecin doit être un identifiant numérique.",
            'medecin_id.exists'   => "Le médecin spécifié est introuvable.",
            'tarif_id.prohibited' => "Le tarif_id n'est pas accepté pour l'instant.",
        ];
    }
}
