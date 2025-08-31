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
        // Tables/colonnes disponibles ?
        $hasTarifs          = Schema::hasTable('tarifs');
        $hasRemisePct       = Schema::hasColumn('visites', 'remise_pct');
        $hasExempt          = Schema::hasColumn('visites', 'exempt');
        $hasMotifGratuite   = Schema::hasColumn('visites', 'motif_gratuite');

        return [
            // patients.id = UUID
            'patient_id' => ['bail','required','uuid','exists:patients,id'],

            // services.id = BIGINT + actif uniquement
            'service_id' => [
                'bail','required','integer',
                Rule::exists('services','id')->where('is_active', true),
            ],

            'plaintes_motif'        => ['nullable','string','max:1000'],
            'hypothese_diagnostic'  => ['nullable','string','max:1000'],

            // users.id = BIGINT
            //'medecin_id'  => ['nullable','integer','exists:users,id'],
            //'medecin_nom' => ['nullable','string','max:150'],

            // toggle d’affectation
            'create_affectation' => ['nullable','boolean'],

            // Pricing conditionnel
            //'tarif_id'       => $hasTarifs ? ['nullable','uuid','exists:tarifs,id'] : ['prohibited'],
            //'remise_pct'     => $hasRemisePct ? ['nullable','numeric','min:0','max:100'] : ['prohibited'],
            //'exempt'         => $hasExempt ? ['nullable','boolean'] : ['prohibited'],
            //'motif_gratuite' => $hasMotifGratuite ? ['nullable','string','max:150'] : ['prohibited'],
        ];
    }

    //public function prepareForValidation(): void
   // {
        // Coercion des types usuels arrivant en string
       // $this->merge([
           // 'service_id'         => $this->service_id !== null ? (int) $this->service_id : null,
            //'medecin_id'         => $this->medecin_id !== null ? (int) $this->medecin_id : null,
            //'create_affectation' => filter_var($this->create_affectation, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            //'exempt'             => filter_var($this->exempt, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        //]);
   // }

    public function messages(): array
    {
        return [
            'patient_id.uuid'     => "Le patient_id doit être un UUID valide.",
            'patient_id.exists'   => "Le patient spécifié est introuvable.",
            'service_id.required' => "Le service est obligatoire.",
            'service_id.integer'  => "Le service doit être un identifiant numérique.",
            'service_id.exists'   => "Le service spécifié est introuvable ou inactif.",
            'medecin_id.integer'  => "Le médecin doit être un identifiant numérique.",
            'medecin_id.exists'   => "Le médecin spécifié est introuvable.",

            //'tarif_id.prohibited'       => "Le champ tarif_id n'est pas accepté (table tarifs absente).",
            //'remise_pct.prohibited'     => "Le champ remise_pct n'est pas accepté (colonne absente).",
            //'exempt.prohibited'         => "Le champ exempt n'est pas accepté (colonne absente).",
            //'motif_gratuite.prohibited' => "Le champ motif_gratuite n'est pas accepté (colonne absente).",
        ];
    }
}
