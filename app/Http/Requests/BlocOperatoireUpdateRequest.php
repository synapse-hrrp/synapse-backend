<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BlocOperatoireUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'        => ['sometimes','uuid','exists:patients,id'],
            'visite_id'         => ['sometimes','nullable','uuid','exists:visites,id'],

            'date_intervention' => ['sometimes','nullable','date'],
            'type_intervention' => ['sometimes','nullable','string','max:190'],
            'cote'              => ['sometimes','nullable','string','max:30'],
            'classification_asa'=> ['sometimes','nullable','string','max:10'],
            'type_anesthesie'   => ['sometimes','nullable','in:generale,rachianesthesie,locale,sedation,autre'],

            'heure_entree_bloc' => ['sometimes','nullable','date_format:H:i'],
            'heure_debut'       => ['sometimes','nullable','date_format:H:i'],
            'heure_fin'         => ['sometimes','nullable','date_format:H:i'],
            'heure_sortie_bloc' => ['sometimes','nullable','date_format:H:i'],

            'indication'        => ['sometimes','nullable','string'],
            'gestes_realises'   => ['sometimes','nullable','string'],
            'compte_rendu'      => ['sometimes','nullable','string'],
            'incident_accident' => ['sometimes','nullable','string'],
            'pertes_sanguines'  => ['sometimes','nullable','string'],
            'antibioprophylaxie'=> ['sometimes','nullable','string'],

            'destination_postop'=> ['sometimes','nullable','in:sspi,reanimation,service,domicile'],
            'consignes_postop'  => ['sometimes','nullable','string'],
            'statut'            => ['sometimes','nullable','in:planifie,en_cours,clos,annule'],

            'chirurgien_id'     => ['sometimes','nullable','integer','exists:users,id'],
            'anesthesiste_id'   => ['sometimes','nullable','integer','exists:users,id'],
            'infirmier_bloc_id' => ['sometimes','nullable','integer','exists:users,id'],
        ];
    }
}
