<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEchographieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ajuste si tu as des policies
    }

    public function rules(): array
    {
        // PATCH/PUT : on utilise mostly `sometimes` pour MAJ partielle
        return [
            'patient_id'       => ['sometimes','exists:patients,id'],
            'service_slug'     => ['sometimes','nullable','string','exists:services,slug'],

            'code_echo'        => ['sometimes','nullable','string','max:50'],
            'nom_echo'         => ['sometimes','nullable','string','max:255'],
            'indication'       => ['sometimes','nullable','string'],
            'statut'           => ['sometimes','in:en_attente,en_cours,termine,valide'],

            'compte_rendu'     => ['sometimes','nullable','string'],
            'conclusion'       => ['sometimes','nullable','string'],

            'mesures_json'     => ['sometimes','nullable','array'],
            'images_json'      => ['sometimes','nullable','array'],
            'images_json.*'    => ['nullable','string','max:2048'], // url/chemin

            'prix'             => ['sometimes','nullable','numeric','min:0'],
            'devise'           => ['sometimes','nullable','string','max:10'],
            'facture_id'       => ['sometimes','nullable','exists:factures,id'],

            'demande_par'      => ['sometimes','nullable','exists:personnels,id'],
            'date_demande'     => ['sometimes','nullable','date'],

            'realise_par'      => ['sometimes','nullable','exists:personnels,id'],
            'date_realisation' => ['sometimes','nullable','date'],

            'valide_par'       => ['sometimes','nullable','exists:personnels,id'],
            'date_validation'  => ['sometimes','nullable','date'],
        ];
    }
}
