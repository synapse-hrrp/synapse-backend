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
            // Ancrages non modifiables
            'patient_id'         => ['prohibited'],
            'service_slug'       => ['prohibited'],
            'tarif_id'           => ['prohibited'],
            'tarif_code'         => ['prohibited'],
            'prix'               => ['prohibited'],
            'devise'             => ['prohibited'],
            'type_origine'       => ['prohibited'],
            'facture_id'         => ['prohibited'],
            'created_via'        => ['prohibited'],
            'created_by_user_id' => ['prohibited'],

            // Éditables
            'code_examen'        => ['sometimes','nullable','string','max:255'],
            'nom_examen'         => ['sometimes','nullable','string','max:255'],

            'prelevement'        => ['sometimes','nullable','string','max:255'],
            'statut'             => ['sometimes','nullable', Rule::in(['en_attente','en_cours','termine','valide'])],

            'valeur_resultat'      => ['sometimes','nullable','string','max:255'],
            'unite'                => ['sometimes','nullable','string','max:255'],
            'intervalle_reference' => ['sometimes','nullable','string','max:255'],
            'resultat_json'        => ['sometimes','nullable','array'],

            'demande_par'        => ['sometimes','nullable','integer','exists:personnels,id'],
            'date_demande'       => ['sometimes','nullable','date'],
            'valide_par'         => ['sometimes','nullable','integer','exists:personnels,id'],
            'date_validation'    => ['sometimes','nullable','date'],
        ];
    }

    public function prepareForValidation(): void
    {
        $statut = $this->statut;
        if (is_string($statut)) $statut = strtolower(trim($statut));

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
        ]);
    }
}
