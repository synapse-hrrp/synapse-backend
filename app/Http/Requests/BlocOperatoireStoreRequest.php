<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BlocOperatoireStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'        => ['required','uuid','exists:patients,id'],
            'visite_id'         => ['nullable','uuid','exists:visites,id'],

            'date_intervention' => ['nullable','date'],
            'type_intervention' => ['nullable','string','max:190'],
            'cote'              => ['nullable','string','max:30'],
            'classification_asa'=> ['nullable','string','max:10'],
            'type_anesthesie'   => ['nullable','in:generale,rachianesthesie,locale,sedation,autre'],

            'heure_entree_bloc' => ['nullable','date_format:H:i','sometimes'],
            'heure_debut'       => ['nullable','date_format:H:i','sometimes'],
            'heure_fin'         => ['nullable','date_format:H:i','sometimes'],
            'heure_sortie_bloc' => ['nullable','date_format:H:i','sometimes'],

            'indication'        => ['nullable','string'],
            'gestes_realises'   => ['nullable','string'],
            'compte_rendu'      => ['nullable','string'],
            'incident_accident' => ['nullable','string'],
            'pertes_sanguines'  => ['nullable','string'],
            'antibioprophylaxie'=> ['nullable','string'],

            'destination_postop'=> ['nullable','in:sspi,reanimation,service,domicile'],
            'consignes_postop'  => ['nullable','string'],
            'statut'            => ['nullable','in:planifie,en_cours,clos,annule'],

            'chirurgien_id'     => ['nullable','integer','exists:users,id'],
            'anesthesiste_id'   => ['nullable','integer','exists:users,id'],
            'infirmier_bloc_id' => ['nullable','integer','exists:users,id'],
        ];
    }
}
