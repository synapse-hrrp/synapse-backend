<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Models\Service;
use App\Models\Tarif;

class VisiteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hasTarifs        = Schema::hasTable('tarifs');
        $hasAffectations  = Schema::hasTable('affectations');
        $servicesHasIsAct = Schema::hasColumn('services','is_active');
        $servicesHasAct   = Schema::hasColumn('services','active');

        return [
            // Patient (UUID)
            'patient_id' => ['bail','required','uuid','exists:patients,id'],

            // Service: accepter id OU slug
            'service_id' => [
                'bail','required_without:service_slug','nullable','integer',
                Rule::exists('services','id')->where(function (Builder $q) use ($servicesHasIsAct, $servicesHasAct) {
                    if ($servicesHasIsAct) { $q->where('is_active', true); }
                    if ($servicesHasAct)   { $q->where('active', true); }
                }),
            ],
            'service_slug' => [
                'bail','required_without:service_id','nullable','string',
                Rule::exists('services','slug')->where(function (Builder $q) use ($servicesHasIsAct, $servicesHasAct) {
                    if ($servicesHasIsAct) { $q->where('is_active', true); }
                    if ($servicesHasAct)   { $q->where('active', true); }
                }),
            ],

            // Médecin (table medecins)
            'medecin_id' => [
                'bail','nullable','integer',
                Rule::exists('medecins','id'),
            ],
            'medecin_nom' => ['nullable','string','max:150'],

            // Données visite
            'heure_arrivee'        => ['bail','nullable','date_format:Y-m-d H:i:s'],
            'plaintes_motif'       => ['nullable','string','max:1000'],
            'hypothese_diagnostic' => ['nullable','string','max:1000'],

            // Affectation (si la table existe → UUID, sinon interdit)
            'create_affectation' => ['nullable','boolean'],
            'affectation_id'     => $hasAffectations
                ? ['nullable','uuid','exists:affectations,id']
                : ['prohibited'],

            // Statut (aligné avec le modèle)
            'statut'  => ['nullable','in:EN_ATTENTE,A_ENCAISSER,PAYEE,CLOTUREE'],
            'clos_at' => ['nullable','date_format:Y-m-d H:i:s'],

            // Tarification (si la table existe)
            'tarif_id'      => $hasTarifs ? ['nullable','uuid','exists:tarifs,id'] : ['prohibited'],
            'tarif_code'    => $hasTarifs ? ['nullable','string','max:50']         : ['prohibited'],
            'montant_prevu' => ['nullable','numeric','min:0'],
            'devise'        => ['nullable','string','size:3'], // ex: XAF
        ];
    }

    public function prepareForValidation(): void
    {
        // Normalisation des types/format avant validation
        $serviceId = $this->service_id !== null ? (int) $this->service_id : null;
        $medecinId = $this->medecin_id !== null ? (int) $this->medecin_id : null;

        $this->merge([
            'service_id'         => $serviceId,
            'medecin_id'         => $medecinId,
            // affectation_id : laissé tel quel (uuid), ne pas caster en int
            'create_affectation' => filter_var($this->create_affectation, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'service_slug'       => $this->service_slug !== null ? trim($this->service_slug) : null,
        ]);
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $v) {
            // --- Vérifier tarif ↔ service (si un tarif est fourni)
            $tarifId = $this->input('tarif_id');
            if ($tarifId) {
                // Résoudre le slug du service
                $serviceSlug = $this->input('service_slug');
                if (! $serviceSlug && $this->filled('service_id')) {
                    $serviceSlug = Service::where('id', (int) $this->input('service_id'))->value('slug');
                }

                if (! $serviceSlug) {
                    $v->errors()->add('service_id', "Service introuvable pour vérifier l'appartenance du tarif.");
                } else {
                    $ok = Tarif::where('id', $tarifId)
                        ->where('service_slug', $serviceSlug)
                        ->exists();
                    if (! $ok) {
                        $v->errors()->add('tarif_id', "Le tarif sélectionné n'appartient pas au service choisi.");
                    }
                }
            }

            // --- Vérifier appartenance du médecin au service (si fourni)
            if (Schema::hasTable('medecins') && Schema::hasTable('personnels') && Schema::hasColumn('personnels','service_id') && $this->filled('medecin_id')) {
                // Résoudre service_id à partir de service_slug si nécessaire
                $serviceId = $this->input('service_id');
                if (! $serviceId && $this->filled('service_slug')) {
                    $serviceId = Service::where('slug', $this->input('service_slug'))->value('id');
                }

                if ($serviceId) {
                    $belongs = DB::table('medecins')
                        ->join('personnels','personnels.id','=','medecins.personnel_id')
                        ->where('medecins.id', (int) $this->input('medecin_id'))
                        ->where('personnels.service_id', (int) $serviceId)
                        ->exists();

                    if (! $belongs) {
                        $v->errors()->add('medecin_id', "Le médecin ne correspond pas au service sélectionné.");
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'patient_id.uuid'     => "Le patient_id doit être un UUID valide.",
            'patient_id.exists'   => "Le patient spécifié est introuvable.",

            'service_id.required_without' => "Le service est obligatoire (id ou slug).",
            'service_id.integer'          => "Le service doit être un identifiant numérique.",
            'service_id.exists'           => "Le service spécifié (par id) est introuvable ou inactif.",

            'service_slug.required_without' => "Le service est obligatoire (slug ou id).",
            'service_slug.exists'           => "Le service spécifié (par slug) est introuvable ou inactif.",

            'medecin_id.integer'  => "Le médecin doit être un identifiant numérique.",
            'medecin_id.exists'   => "Le médecin spécifié est introuvable.",

            'heure_arrivee.date_format' => "L'heure d'arrivée doit respecter le format Y-m-d H:i:s.",

            'statut.in'                 => "Le statut doit être parmi: EN_ATTENTE, A_ENCAISSER, PAYEE, CLOTUREE.",
            'clos_at.date_format'       => "La date de clôture doit respecter le format Y-m-d H:i:s.",

            'tarif_id.exists'           => "Le tarif sélectionné est introuvable.",
            'montant_prevu.numeric'     => "Le montant doit être un nombre.",
            'montant_prevu.min'         => "Le montant doit être positif.",
            'devise.size'               => "La devise doit être un code de 3 lettres (ex: XAF).",
        ];
    }
}
