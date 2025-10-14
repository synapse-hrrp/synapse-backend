<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHospitalisationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'           => ['sometimes','exists:patients,id'],
            'service_slug'         => ['sometimes','nullable','string','exists:services,slug'],
            'admission_no'         => ['sometimes','nullable','string','max:100'],

            'unite'                => ['sometimes','nullable','string','max:100'],
            'chambre'              => ['sometimes','nullable','string','max:100'],
            'lit'                  => ['sometimes','nullable','string','max:100'],
            'lit_id'               => ['sometimes','nullable','integer'],
            'medecin_traitant_id'  => ['sometimes','nullable','exists:personnels,id'],

            'motif_admission'      => ['sometimes','nullable','string'],
            'diagnostic_entree'    => ['sometimes','nullable','string'],
            'diagnostic_sortie'    => ['sometimes','nullable','string'],
            'notes'                => ['sometimes','nullable','string'],
            'prise_en_charge_json' => ['sometimes','nullable','array'],

            'statut'               => ['sometimes','in:en_cours,transfere,sorti,annule'],
            'date_admission'       => ['sometimes','nullable','date'],
            'date_sortie_prevue'   => ['sometimes','nullable','date'],
            'date_sortie_reelle'   => ['sometimes','nullable','date'],

            'facture_id'           => ['sometimes','nullable','exists:factures,id'],
        ];
    }
}
