<?php
// app/Http/Requests/ExamenStoreRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Models\Tarif;

class ExamenStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $hasTarifs   = Schema::hasTable('tarifs');
        $hasServices = Schema::hasTable('services');

        return [
            // Patient
            'patient_id'   => ['bail','required','uuid','exists:patients,id'],

            // Service demandeur (traçabilité)
            'service_slug' => $hasServices
                ? ['bail','nullable','string', Rule::exists('services','slug')]
                : ['prohibited'],

            // Infos médicales
            'prelevement'  => ['nullable','string','max:255'],
            'demande_par'  => ['nullable','integer','exists:medecins,id'],
            'date_demande' => ['nullable','date'],

            // Résultats (optionnels)
            'valeur_resultat'      => ['nullable','string','max:255'],
            'unite'                => ['nullable','string','max:255'],
            'intervalle_reference' => ['nullable','string','max:255'],
            'resultat_json'        => ['nullable','array'],

            // Statut
            'statut' => ['nullable', Rule::in(['en_attente','en_cours','termine','valide'])],

            // Tarification labo (facultative)
            'tarif_id'   => $hasTarifs ? ['nullable','uuid','exists:tarifs,id'] : ['prohibited'],
            'tarif_code' => $hasTarifs ? ['nullable','string','max:50']         : ['prohibited'],

            // Libellés d'examen (indépendants de la tarification)
            'code_examen' => ['nullable','string','max:255'],
            'nom_examen'  => ['nullable','string','max:255'],

            // Prix manuel (seulement si pas de tarif)
            'prix'   => ['nullable','numeric','min:0','prohibited_with:tarif_id,tarif_code'],
            'devise' => ['nullable','string','size:3','prohibited_with:tarif_id,tarif_code'],

            // Champs système gérés ailleurs
            'type_origine'        => ['prohibited'],
            'facture_id'          => ['prohibited'],
            'valide_par'          => ['prohibited'],
            'date_validation'     => ['prohibited'],
            'created_via'         => ['prohibited'],
            'created_by_user_id'  => ['prohibited'],
        ];
    }

    public function prepareForValidation(): void
    {
        $slug      = $this->service_slug !== null ? trim($this->service_slug) : null;
        $tarifCode = $this->tarif_code  !== null ? strtoupper(trim($this->tarif_code)) : null;
        $codeExam  = $this->code_examen !== null ? strtoupper(trim($this->code_examen)) : null;
        $devise    = $this->devise      !== null ? strtoupper(trim($this->devise)) : null;

        $this->merge([
            'service_slug' => $slug,
            'tarif_code'   => $tarifCode,
            'code_examen'  => $codeExam,
            'devise'       => $devise,
            'demande_par'  => $this->demande_par !== null ? (int) $this->demande_par : null,
        ]);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $tarifId   = $this->input('tarif_id');
            $tarifCode = $this->input('tarif_code') ? strtoupper(trim($this->input('tarif_code'))) : null;
            $prix      = $this->input('prix');

            $hasTarif = (bool) ($tarifId || $tarifCode);
            $hasPrix  = ($prix !== null && $prix !== '');

            // Exiger une source de prix (tarif OU prix manuel)
            if (!$hasTarif && !$hasPrix) {
                $validator->errors()->add('tarif_code', "Indiquez un tarif labo (tarif_id ou tarif_code) ou un prix manuel.");
                return;
            }

            // Si tarif → vérifier qu'il est bien rattaché à un service de labo
            if ($hasTarif) {
                $labSlugs = config('billing.lab_service_slugs', ['laboratoire','labo','examens']);

                if ($tarifId) {
                    $ok = Tarif::query()
                        ->whereKey($tarifId)
                        ->whereIn('service_slug', $labSlugs)
                        ->exists();
                    if (!$ok) {
                        $validator->errors()->add('tarif_id', "Le tarif sélectionné n'appartient pas aux services de laboratoire autorisés.");
                        return;
                    }
                }

                if ($tarifCode) {
                    $q = Tarif::query()
                        ->actifs()
                        ->byCode($tarifCode)
                        ->whereIn('service_slug', $labSlugs);

                    $count = $q->count();
                    if ($count === 0) {
                        $validator->errors()->add('tarif_code', "Aucun tarif actif (labo) trouvé pour le code {$tarifCode}.");
                        return;
                    }
                    if ($count > 1) {
                        $validator->errors()->add('tarif_code', "Plusieurs tarifs labo partagent ce code. Utilisez 'tarif_id'.");
                        return;
                    }
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'patient_id'     => 'patient',
            'service_slug'   => 'service demandeur',
            'tarif_id'       => 'tarif',
            'tarif_code'     => 'code de tarification',
            'code_examen'    => "code de l'examen",
            'nom_examen'     => "nom de l'examen",
            'prix'           => 'prix',
            'devise'         => 'devise',
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.uuid'     => "Le patient_id doit être un UUID valide.",
            'patient_id.exists'   => "Le patient spécifié est introuvable.",
            'service_slug.exists' => "Le service spécifié (slug) est introuvable.",
        ];
    }
}
