<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KinesitherapieStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id'       => ['required','uuid','exists:patients,id'],
            'visite_id'        => ['nullable','uuid','exists:visites,id'],

            'date_acte'        => ['nullable','date'],
            'motif'            => ['nullable','string','max:190'],
            'diagnostic'       => ['nullable','string','max:190'],
            'evaluation'       => ['nullable','string'],
            'objectifs'        => ['nullable','string'],
            'techniques'       => ['nullable','string'],
            'zone_traitee'     => ['nullable','string','max:190'],
            'intensite_douleur'=> ['nullable','integer','min:0','max:10'],
            'echelle_borg'     => ['nullable','integer','min:0','max:10'],
            'nombre_seances'   => ['nullable','integer','min:0','max:1000'],
            'duree_minutes'    => ['nullable','integer','min:0','max:600'],
            'resultats'        => ['nullable','string'],
            'conseils'         => ['nullable','string'],
            'statut'           => ['nullable','in:planifie,en_cours,termine,annule'],
        ];
    }
}
