<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GestionMaladeUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'patient_id' => ['sometimes','uuid','exists:patients,id'],
            'visite_id'  => ['sometimes','nullable','uuid','exists:visites,id'],

            'date_acte'  => ['sometimes','nullable','date'],

            'type_action' => ['sometimes','nullable','in:admission,transfert,hospitalisation,sortie'],

            'service_source'      => ['sometimes','nullable','string','max:120'],
            'service_destination' => ['sometimes','nullable','string','max:120'],
            'pavillon'            => ['sometimes','nullable','string','max:120'],
            'chambre'             => ['sometimes','nullable','string','max:60'],
            'lit'                 => ['sometimes','nullable','string','max:60'],

            'date_entree'           => ['sometimes','nullable','date'],
            'date_sortie_prevue'    => ['sometimes','nullable','date'],
            'date_sortie_effective' => ['sometimes','nullable','date'],

            'motif'           => ['sometimes','nullable','string','max:190'],
            'diagnostic'      => ['sometimes','nullable','string','max:190'],
            'examen_clinique' => ['sometimes','nullable','string'],
            'traitements'     => ['sometimes','nullable','string'],
            'observation'     => ['sometimes','nullable','string'],

            'statut' => ['sometimes','nullable','in:en_cours,clos,annule'],
        ];
    }
}
