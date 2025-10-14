<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHospitalisationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'           => ['required','exists:patients,id'],
            'service_slug'         => ['nullable','string','exists:services,slug'],
            'admission_no'         => ['nullable','string','max:100'],

            'unite'                => ['nullable','string','max:100'],
            'chambre'              => ['nullable','string','max:100'],
            'lit'                  => ['nullable','string','max:100'],
            'lit_id'               => ['nullable','integer'],
            'medecin_traitant_id'  => ['nullable','exists:personnels,id'],

            'motif_admission'      => ['nullable','string'],
            'diagnostic_entree'    => ['nullable','string'],
            'diagnostic_sortie'    => ['nullable','string'],
            'notes'                => ['nullable','string'],
            'prise_en_charge_json' => ['nullable','array'],

            'statut'               => ['nullable','in:en_cours,transfere,sorti,annule'],
            'date_admission'       => ['nullable','date'],
            'date_sortie_prevue'   => ['nullable','date'],
            'date_sortie_reelle'   => ['nullable','date'],

            'facture_id'           => ['nullable','exists:factures,id'],
        ];
    }
}
