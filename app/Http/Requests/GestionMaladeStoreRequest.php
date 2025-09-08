<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GestionMaladeStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id' => ['required','uuid','exists:patients,id'],
            'visite_id'  => ['nullable','uuid','exists:visites,id'],

            'date_acte'  => ['nullable','date'],

            'type_action' => ['nullable','in:admission,transfert,hospitalisation,sortie'],

            'service_source'      => ['nullable','string','max:120'],
            'service_destination' => ['nullable','string','max:120'],
            'pavillon'            => ['nullable','string','max:120'],
            'chambre'             => ['nullable','string','max:60'],
            'lit'                 => ['nullable','string','max:60'],

            'date_entree'           => ['nullable','date'],
            'date_sortie_prevue'    => ['nullable','date'],
            'date_sortie_effective' => ['nullable','date'],

            'motif'           => ['nullable','string','max:190'],
            'diagnostic'      => ['nullable','string','max:190'],
            'examen_clinique' => ['nullable','string'],
            'traitements'     => ['nullable','string'],
            'observation'     => ['nullable','string'],

            'statut' => ['nullable','in:en_cours,clos,annule'],
        ];
    }
}
