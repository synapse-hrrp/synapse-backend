<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBilletSortieRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'      => ['required','exists:patients,id'],
            'service_slug'    => ['nullable','string','exists:services,slug'],
            'admission_id'    => ['nullable','exists:admissions,id'],

            'motif_sortie'    => ['nullable','string','max:255'],
            'diagnostic_sortie' => ['nullable','string'],
            'resume_clinique' => ['nullable','string'],
            'consignes'       => ['nullable','string'],
            'traitement_sortie_json' => ['nullable','array'],
            'rdv_controle_at' => ['nullable','date'],
            'destination'     => ['nullable','string','max:255'],

            'statut'          => ['nullable','in:brouillon,valide,remis'],
            'remis_a'         => ['nullable','string','max:255'],
            'signature_par'   => ['nullable','exists:personnels,id'],
            'date_signature'  => ['nullable','date'],
            'date_sortie_effective' => ['nullable','date'],
        ];
    }
}
