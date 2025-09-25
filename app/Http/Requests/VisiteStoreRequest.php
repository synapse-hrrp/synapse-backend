<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Models\Service;
use App\Models\Tarif;

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
            // Patient = UUID
            'patient_id' => ['bail','required','uuid','exists:patients,id'],

            // Service: accepter id OU slug
            'service_id' => [
                'bail','required_without:service_slug','nullable','integer',
                Rule::exists('services','id')->where(function($q) use ($servicesHasIsAct,$servicesHasAct){
                    if ($servicesHasIsAct) $q->where('is_active', true);
                    if ($servicesHasAct)   $q->where('active', true);
                }),
            ],
            'service_slug' => [
                'bail','required_without:service_id','nullable','string',
                Rule::exists('services','slug')->where(function($q) use ($servicesHasIsAct,$servicesHasAct){
                    if ($servicesHasIsAct) $q->where('is_active', true);
                    if ($servicesHasAct)   $q->where('active', true);
                }),
            ],

            // Médecin: doit appartenir au service si personnels.service_id existe
            'medecin_id' => [
                'bail','nullable','integer',
                Rule::exists('personnels','id')->where(function($q) use ($personnelsHasSvc) {
                    if ($personnelsHasSvc) {
                        $serviceId = $this->input('service_id');
                        if (!$serviceId && $this->filled('service_slug')) {
                            $serviceId = Service::where('slug', $this->input('service_slug'))->value('id');
                        }
                        if ($serviceId) $q->where('service_id', (int) $serviceId);
                    }
                }),
            ],
            'medecin_nom' => ['nullable','string','max:150'],

            // Données visite
            'heure_arrivee'        => ['bail','nullable','date_format:Y-m-d H:i:s'],
            'plaintes_motif'       => ['nullable','string','max:1000'],
            'hypothese_diagnostic' => ['nullable','string','max:1000'],

            // Affectation (optionnelle si la table existe)
            'create_affectation' => ['nullable','boolean'],
            'affectation_id'     => $hasAffectations
                ? ['nullable','integer','exists:affectations,id']
                : ['prohibited'],

            // Statut (aligné avec le modèle)
            'statut'  => ['nullable','in:EN_ATTENTE,A_ENCAISSER,PAYEE,CLOTUREE'],
            'clos_at' => ['nullable','date_format:Y-m-d H:i:s'],

            // Tarification minimale
            'tarif_id'      => $hasTarifs ? ['nullable','uuid','exists:tarifs,id'] : ['prohibited'],
            'tarif_code'    => $hasTarifs ? ['nullable','string','max:50']         : ['prohibited'],
            'montant_prevu' => ['nullable','numeric','min:0'],
            'devise'        => ['nullable','string','size:3'], // ex. XAF
        ];
    }

    public function prepareForValidation(): void
    {
        $serviceId = $this->service_id !== null ? (int) $this->service_id : null;

        $this->merge([
            'service_id'         => $serviceId,
            'medecin_id'         => $this->medecin_id !== null ? (int) $this->medecin_id : null,
            'affectation_id'     => $this->affectation_id !== null ? (int) $this->affectation_id : null,
            'create_affectation' => filter_var($this->create_affectation, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'service_slug'       => $this->service_slug !== null ? trim($this->service_slug) : null,
        ]);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Vérifier que le tarif (si fourni) appartient au service choisi (via slug)
            $tarifId = $this->input('tarif_id');
            if (! $tarifId) return;

            // Résoudre le slug du service (depuis id ou directement)
            $serviceSlug = $this->input('service_slug');
            if (! $serviceSlug && $this->filled('service_id')) {
                $serviceSlug = Service::where('id', (int)$this->input('service_id'))->value('slug');
            }

            if (! $serviceSlug) {
                $validator->errors()->add('service_id', "Service introuvable pour vérifier l'appartenance du tarif.");
                return;
            }

            $ok = Tarif::where('id', $tarifId)
                ->where('service_slug', $serviceSlug)
                ->exists();

            if (! $ok) {
                $validator->errors()->add('tarif_id', "Le tarif sélectionné n'appartient pas au service choisi.");
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
            'medecin_id.exists'   => "Le médecin spécifié est introuvable dans ce service.",

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
