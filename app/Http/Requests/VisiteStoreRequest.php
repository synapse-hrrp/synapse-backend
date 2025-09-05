<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class VisiteStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        // Tables / colonnes optionnelles (si tu veux que ce soit dynamique)
        $hasTarifs = Schema::hasTable('tarifs');

        return [
            // patients.id = UUID
            'patient_id' => ['bail','required','uuid','exists:patients,id'],

            // services.id = BIGINT + actif uniquement
            'service_id' => [
                'bail','required','integer',
                Rule::exists('services','id')->where('is_active', true),
            ],

            // Médecin : appartient au service choisi (si tu utilises la table "personnels")
            'medecin_id' => [
                'bail','nullable','integer',
                Rule::exists('personnels','id')->where(function($q) {
                    $q->where('service_id', $this->input('service_id'));
                    // Variante éventuelle si tu veux filtrer le métier :
                    // $q->where('is_medecin', true);
                }),
            ],
            'medecin_nom' => ['nullable','string','max:150'],

            // Données visite
            // FACULTATIF: on laisse le modèle poser now() si absent
            'heure_arrivee'        => ['bail','nullable','date_format:Y-m-d H:i:s'],
            'plaintes_motif'       => ['nullable','string','max:1000'],
            'hypothese_diagnostic' => ['nullable','string','max:1000'],

            // Affectation : optionnelle
            'create_affectation'   => ['nullable','boolean'],
            'affectation_id'       => ['nullable','integer','exists:affectations,id'],

            // Statut (app attend en_cours, le contrôleur convertit vers "en cours" si besoin)
            'statut'               => ['nullable','in:en_cours,clos'],
            'clos_at'              => ['nullable','date_format:Y-m-d H:i:s'],

            // -------- Tarification MINIMALE --------
            // Choisir un tarif par id OU code (pratique pour le front)
            'tarif_id'   => $hasTarifs ? ['nullable','uuid','exists:tarifs,id'] : ['prohibited'],
            'tarif_code' => $hasTarifs ? ['nullable','string','max:50']         : ['prohibited'],

            // Si aucun tarif n’est choisi, on peut saisir un prix manuel
            'montant_prevu' => ['nullable','numeric','min:0'],
            'devise'        => ['nullable','string','max:8'],
        ];
    }

    public function prepareForValidation(): void
    {
        // Coercions usuelles (int/bool) quand ça arrive en string
        $this->merge([
            'service_id'         => $this->service_id !== null ? (int) $this->service_id : null,
            'medecin_id'         => $this->medecin_id !== null ? (int) $this->medecin_id : null,
            'affectation_id'     => $this->affectation_id !== null ? (int) $this->affectation_id : null,
            'create_affectation' => filter_var($this->create_affectation, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        ]);
    }

    public function messages(): array
    {
        return [
            'patient_id.uuid'     => "Le patient_id doit être un UUID valide.",
            'patient_id.exists'   => "Le patient spécifié est introuvable.",
            'service_id.required' => "Le service est obligatoire.",
            'service_id.integer'  => "Le service doit être un identifiant numérique.",
            'service_id.exists'   => "Le service spécifié est introuvable ou inactif.",

            'medecin_id.integer'  => "Le médecin doit être un identifiant numérique.",
            'medecin_id.exists'   => "Le médecin spécifié est introuvable dans ce service.",

            'heure_arrivee.date_format' => "L'heure d'arrivée doit respecter le format Y-m-d H:i:s.",
            'statut.in'                 => "Le statut doit être 'en_cours' ou 'clos'.",
            'clos_at.date_format'       => "La date de clôture doit respecter le format Y-m-d H:i:s.",

            'tarif_id.exists'  => "Le tarif sélectionné est introuvable.",
            'montant_prevu.numeric' => "Le montant doit être un nombre.",
            'montant_prevu.min'     => "Le montant doit être positif.",
            'devise.max'            => "La devise ne peut dépasser 8 caractères.",
        ];
    }
}
