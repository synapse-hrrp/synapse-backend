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
        $hasTarifs        = Schema::hasTable('tarifs');
        $hasAffectations  = Schema::hasTable('affectations');
        $personnelsHasSvc = Schema::hasColumn('personnels','service_id');
        $servicesHasIsAct = Schema::hasColumn('services','is_active');
        $servicesHasAct   = Schema::hasColumn('services','active');

        return [
            // patients.id = UUID
            'patient_id' => ['bail','required','uuid','exists:patients,id'],

            // services.id (actif seulement si la/les colonnes existent)
            'service_id' => [
                'bail','required','integer',
                Rule::exists('services','id')->where(function($q) use ($servicesHasIsAct,$servicesHasAct){
                    if ($servicesHasIsAct) $q->where('is_active', true);
                    if ($servicesHasAct)   $q->where('active', true);
                }),
            ],

            // medecin_id doit appartenir au service SEULEMENT si personnels.service_id existe
            'medecin_id' => [
                'bail','nullable','integer',
                Rule::exists('personnels','id')->where(function($q) use ($personnelsHasSvc){
                    if ($personnelsHasSvc && $this->filled('service_id')) {
                        $q->where('service_id', (int) $this->input('service_id'));
                    }
                }),
            ],
            'medecin_nom' => ['nullable','string','max:150'],

            // Données visite
            'heure_arrivee'        => ['bail','nullable','date_format:Y-m-d H:i:s'],
            'plaintes_motif'       => ['nullable','string','max:1000'],
            'hypothese_diagnostic' => ['nullable','string','max:1000'],

            // Affectation optionnelle (prohibée si table absente)
            'create_affectation' => ['nullable','boolean'],
            'affectation_id'     => $hasAffectations
                ? ['nullable','integer','exists:affectations,id']
                : ['prohibited'],

            // Statut
            'statut'  => ['nullable','in:en_cours,clos'],
            'clos_at' => ['nullable','date_format:Y-m-d H:i:s'],

            // Tarification minimale (prohibée si table absente)
            'tarif_id'   => $hasTarifs ? ['nullable','uuid','exists:tarifs,id'] : ['prohibited'],
            'tarif_code' => $hasTarifs ? ['nullable','string','max:50']         : ['prohibited'],

            'montant_prevu' => ['nullable','numeric','min:0'],
            'devise'        => ['nullable','string','max:8'],
        ];
    }

    public function prepareForValidation(): void
    {
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
            'tarif_id.exists'           => "Le tarif sélectionné est introuvable.",
            'montant_prevu.numeric'     => "Le montant doit être un nombre.",
            'montant_prevu.min'         => "Le montant doit être positif.",
            'devise.max'                => "La devise ne peut dépasser 8 caractères.",
        ];
    }
}
