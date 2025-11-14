<?php
// app/Http/Requests/ExamenUpdateRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamenUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Non modifiables
            'patient_id'         => ['prohibited'],
            'service_slug'       => ['prohibited'],
            'tarif_id'           => ['prohibited'],
            'tarif_code'         => ['prohibited'],
            'type_origine'       => ['prohibited'],
            'facture_id'         => ['prohibited'],
            'created_via'        => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'date_demande'       => ['prohibited'],   // <= gérée auto
            'date_validation'    => ['prohibited'],   // <= gérée auto

            // Éditables
            'code_examen'          => ['sometimes','nullable','string','max:255'],
            'nom_examen'           => ['sometimes','nullable','string','max:255'],
            'prelevement'          => ['sometimes','nullable','string','max:255'],
            'statut'               => ['sometimes','nullable', Rule::in(['en_attente','en_cours','termine','valide'])],

            'valeur_resultat'      => ['sometimes','nullable','string','max:255'],
            'unite'                => ['sometimes','nullable','string','max:255'],
            'intervalle_reference' => ['sometimes','nullable','string','max:255'],
            'resultat_json'        => ['sometimes','nullable','array'],

            // Traçabilité
            'demande_par'          => ['sometimes','nullable','integer','exists:medecins,id'],
            'valide_par'           => ['sometimes','nullable','integer','exists:personnels,id'],

            // Tarifs manuels autorisés en update (le modèle recalcule de tte façon si code_examen/service_slug changent)
            'prix'                 => ['sometimes','nullable','numeric','min:0'],
            'devise'               => ['sometimes','nullable','string','size:3'],

            // prescripteur externe éditable mais sera vidé si interne côté modèle
            'prescripteur_externe' => ['sometimes','nullable','string','max:255'],
            'reference_demande'    => ['sometimes','nullable','string','max:255'],
        ];
    }

    public function prepareForValidation(): void
    {
        $statut = $this->statut;
        if (is_string($statut)) {
            $statut = strtolower(trim($statut));
        }

        $this->merge([
            'statut'               => $statut,
            'code_examen'          => $this->code_examen !== null ? strtoupper(trim($this->code_examen)) : null,
            'nom_examen'           => $this->nom_examen !== null ? trim($this->nom_examen) : null,
            'prelevement'          => $this->prelevement !== null ? trim($this->prelevement) : null,
            'valeur_resultat'      => $this->valeur_resultat !== null ? trim($this->valeur_resultat) : null,
            'unite'                => $this->unite !== null ? trim($this->unite) : null,
            'intervalle_reference' => $this->intervalle_reference !== null ? trim($this->intervalle_reference) : null,
            'demande_par'          => $this->demande_par !== null ? (int) $this->demande_par : null,
            'valide_par'           => $this->valide_par  !== null ? (int) $this->valide_par  : null,
            'devise'               => $this->devise !== null ? strtoupper(trim($this->devise)) : null,
        ]);
    }

    public function attributes(): array
    {
        return [
            'code_examen'          => "code de l'examen",
            'nom_examen'           => "nom de l'examen",
            'prelevement'          => 'prélèvement',
            'valeur_resultat'      => 'valeur du résultat',
            'intervalle_reference' => 'intervalle de référence',
            'demande_par'          => 'médecin demandeur',
            'valide_par'           => 'validateur',
            'prescripteur_externe' => 'prescripteur externe',
        ];
    }
}
