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
        // Tables / colonnes optionnelles
        $hasTarifs        = Schema::hasTable('tarifs');
        $hasRemisePct     = Schema::hasColumn('visites', 'remise_pct');
        $hasExempt        = Schema::hasColumn('visites', 'exempt');
        $hasMotifGratuite = Schema::hasColumn('visites', 'motif_gratuite');

        return [
            // patients.id = UUID
            'patient_id' => ['bail','required','uuid','exists:patients,id'],

            // services.id = BIGINT + actif uniquement
            'service_id' => [
                'bail','required','integer',
                Rule::exists('services','id')->where('is_active', true),
            ],

            // Médecin : provient de personnels + appartient au service choisi
            'medecin_id' => [
                'bail','required','integer',
                Rule::exists('personnels','id')->where(function($q) {
                    $q->where('service_id', $this->input('service_id'));

                    // —— Choisir UNE des deux variantes ci-dessous ——
                    // Variante A : si tu as un booléen sur personnels
                    // $q->where('is_medecin', true);

                    // Variante B : si tu identifies via job_title (sans booléen)
                    // $q->where(function($w){
                    //     $w->where('job_title','like','Médecin%')
                    //       ->orWhere('job_title','like','Medecin%')
                    //       ->orWhere('job_title','like','Docteur%');
                    // });
                }),
            ],

            // Données visite
            'heure_arrivee'        => ['bail','required','date_format:Y-m-d H:i:s'],
            'plaintes_motif'       => ['nullable','string','max:1000'],
            'hypothese_diagnostic' => ['nullable','string','max:1000'],

            // Affectation : optionnelle
            'create_affectation'   => ['nullable','boolean'],
            'affectation_id'       => ['nullable','integer','exists:affectations,id'],

            // Statut / clôture (optionnels, avec contraintes)
            'statut'               => ['nullable','in:en_cours,clos'],
            'clos_at'              => ['nullable','date_format:Y-m-d H:i:s'],

            // Tarification (si activée dans le schéma)
            // 'tarif_id'       => $hasTarifs ? ['nullable','uuid','exists:tarifs,id'] : ['prohibited'],
            // 'remise_pct'     => $hasRemisePct ? ['nullable','numeric','min:0','max:100'] : ['prohibited'],
            // 'exempt'         => $hasExempt ? ['nullable','boolean'] : ['prohibited'],
            // 'motif_gratuite' => $hasMotifGratuite ? ['nullable','string','max:150'] : ['prohibited'],
        ];
    }

    public function prepareForValidation(): void
    {
        // Coercion des types usuels (int/bool) quand ça arrive en string
        $this->merge([
            'service_id'         => $this->service_id !== null ? (int) $this->service_id : null,
            'medecin_id'         => $this->medecin_id !== null ? (int) $this->medecin_id : null,
            'affectation_id'     => $this->affectation_id !== null ? (int) $this->affectation_id : null,
            'create_affectation' => filter_var($this->create_affectation, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            // 'exempt'          => filter_var($this->exempt, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
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

            'medecin_id.required' => "Le médecin est obligatoire.",
            'medecin_id.integer'  => "Le médecin doit être un identifiant numérique.",
            'medecin_id.exists'   => "Le médecin spécifié est introuvable dans ce service.",

            'heure_arrivee.required'    => "L'heure d'arrivée est obligatoire.",
            'heure_arrivee.date_format' => "L'heure d'arrivée doit respecter le format Y-m-d H:i:s.",

            'statut.in'           => "Le statut doit être 'en_cours' ou 'clos'.",
            'clos_at.date_format' => "La date de clôture doit respecter le format Y-m-d H:i:s.",

            // 'tarif_id.prohibited'       => "Le champ tarif_id n'est pas accepté (table tarifs absente).",
            // 'remise_pct.prohibited'     => "Le champ remise_pct n'est pas accepté (colonne absente).",
            // 'exempt.prohibited'         => "Le champ exempt n'est pas accepté (colonne absente).",
            // 'motif_gratuite.prohibited' => "Le champ motif_gratuite n'est pas accepté (colonne absente).",
        ];
    }
}
