<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBilletSortieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id'            => ['sometimes','uuid','exists:patients,id'],
            'service_slug'          => ['sometimes','nullable','string','exists:services,slug'],
            'admission_id'          => ['sometimes','nullable','uuid'],

            'created_via'           => ['sometimes','nullable','in:service,med,admin'],

            'motif_sortie'          => ['sometimes','nullable','string','max:100'],
            'diagnostic_sortie'     => ['sometimes','nullable','string'],
            'resume_clinique'       => ['sometimes','nullable','string'],
            'consignes'             => ['sometimes','nullable','string'],
            'traitement_sortie_json'=> ['sometimes','nullable','array'],
            'rdv_controle_at'       => ['sometimes','nullable','date'],
            'destination'           => ['sometimes','nullable','string','max:150'],

            'statut'                => ['sometimes','nullable','in:brouillon,valide,remis'],
            'remis_a'               => ['sometimes','nullable','string','max:150'],
            'signature_par'         => ['sometimes','nullable','uuid','exists:personnels,id'],
            'date_signature'        => ['sometimes','nullable','date'],
            'date_sortie_effective' => ['sometimes','nullable','date'],

            'prix'                  => ['prohibited'],
            'devise'                => ['prohibited'],
            'facture_id'            => ['prohibited'],
            'created_by_user_id'    => ['prohibited'],
        ];
    }
}
