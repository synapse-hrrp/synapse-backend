<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KinesitherapieUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id'        => ['sometimes','uuid','exists:patients,id'],
            'visite_id'         => ['sometimes','nullable','uuid','exists:visites,id'],

            'date_acte'         => ['sometimes','nullable','date'],
            'motif'             => ['sometimes','nullable','string','max:190'],
            'diagnostic'        => ['sometimes','nullable','string','max:190'],
            'evaluation'        => ['sometimes','nullable','string'],
            'objectifs'         => ['sometimes','nullable','string'],
            'techniques'        => ['sometimes','nullable','string'],
            'zone_traitee'      => ['sometimes','nullable','string','max:190'],

            'intensite_douleur' => ['sometimes','nullable','integer','between:0,10'],
            'echelle_borg'      => ['sometimes','nullable','integer','between:0,10'],
            'nombre_seances'    => ['sometimes','nullable','integer','min:0','max:1000'],
            'duree_minutes'     => ['sometimes','nullable','integer','min:0','max:600'],

            'resultats'         => ['sometimes','nullable','string'],
            'conseils'          => ['sometimes','nullable','string'],

            'statut'            => ['sometimes','in:planifie,en_cours,termine,annule'],
        ];
    }
}
