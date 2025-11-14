<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use App\Models\Service;
use App\Models\Tarif;

class VisiteUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $hasTarifs = Schema::hasTable('tarifs');

        return [
            'patient_id'    => ['prohibited'],
            'agent_id'      => ['prohibited'],
            'agent_nom'     => ['prohibited'],
            'heure_arrivee' => ['prohibited'],

            'service_id'   => ['sometimes','integer','exists:services,id'],
            'service_slug' => ['sometimes','string','exists:services,slug'],

            'plaintes_motif'        => ['sometimes','nullable','string'],
            'hypothese_diagnostic'  => ['sometimes','nullable','string'],

            // ✅ medecins.id
            'medecin_id'            => ['sometimes','nullable','integer', Rule::exists('medecins','id')],
            'medecin_nom'           => ['sometimes','nullable','string','max:150'],

            'statut'                => ['sometimes','in:EN_ATTENTE,A_ENCAISSER,PAYEE,CLOTUREE'],
            'clos_at'               => ['sometimes','nullable','date_format:Y-m-d H:i:s'],

            'tarif_id'              => $hasTarifs ? ['sometimes','nullable','uuid','exists:tarifs,id'] : ['prohibited'],
            'montant_prevu'         => ['sometimes','nullable','numeric','min:0'],
            'montant_du'            => ['sometimes','nullable','numeric','min:0'],
            'devise'                => ['sometimes','nullable','string','size:3'],
        ];
    }

    public function withValidator(ValidatorContract $v): void
    {
        $v->after(function (ValidatorContract $v) {
            // 1) Vérifier tarif ↔ service
            $serviceSlug = $this->input('service_slug');
            $serviceId   = $this->input('service_id');

            if (! $serviceSlug && $serviceId) {
                $serviceSlug = Service::where('id', (int)$serviceId)->value('slug');
            }

            $tarifId = $this->input('tarif_id');
            if ($tarifId) {
                if (! $serviceSlug) {
                    if ($this->route('visite') && ! $serviceId) {
                        $serviceId = optional($this->route('visite'))->service_id;
                        if ($serviceId) {
                            $serviceSlug = Service::where('id', (int)$serviceId)->value('slug');
                        }
                    }
                }

                if (! $serviceSlug) {
                    $v->errors()->add('service_id', "Impossible de vérifier l'appartenance du tarif: service manquant.");
                } else {
                    $ok = Tarif::where('id', $tarifId)
                        ->where('service_slug', $serviceSlug)
                        ->exists();
                    if (! $ok) {
                        $v->errors()->add('tarif_id', "Le tarif sélectionné n'appartient pas au service choisi.");
                    }
                }
            }

            // 2) Vérifier appartenance medecin->service
            if (Schema::hasTable('medecins') && Schema::hasTable('personnels') && Schema::hasColumn('personnels','service_id') && $this->filled('medecin_id')) {
                $resolvedServiceId = $serviceId;
                if (! $resolvedServiceId && $serviceSlug) {
                    $resolvedServiceId = Service::where('slug', $serviceSlug)->value('id');
                }
                if (! $resolvedServiceId && $this->route('visite')) {
                    $resolvedServiceId = optional($this->route('visite'))->service_id;
                }
                if ($resolvedServiceId) {
                    $belongs = DB::table('medecins')
                        ->join('personnels','personnels.id','=','medecins.personnel_id')
                        ->where('medecins.id', (int)$this->input('medecin_id'))
                        ->where('personnels.service_id', (int)$resolvedServiceId)
                        ->exists();

                    if (! $belongs) {
                        $v->errors()->add('medecin_id', "Le médecin ne correspond pas au service sélectionné.");
                    }
                }
            }
        });
    }
}
