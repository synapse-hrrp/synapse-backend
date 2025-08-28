<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;

class VisiteUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $hasTarifs = Schema::hasTable('tarifs');

        return [
            // 🔒 On n’autorise pas à changer ces champs via update
            'patient_id'     => ['prohibited'],
            'agent_id'       => ['prohibited'],
            'agent_nom'      => ['prohibited'],
            'heure_arrivee'  => ['prohibited'],

            // ✅ Champs modifiables
            'service_id'            => ['sometimes','uuid','exists:services,id'], // <- remplace service_code
            // (option) si tu conserves un snapshot service_code SANS FK dans visites :
            // 'service_code'       => ['sometimes','string','max:64'],

            'plaintes_motif'        => ['sometimes','nullable','string'],
            'hypothese_diagnostic'  => ['sometimes','nullable','string'],
            'medecin_id'            => ['sometimes','nullable','uuid','exists:users,id'],
            'medecin_nom'           => ['sometimes','nullable','string','max:150'],
            'statut'                => ['sometimes','in:ouvert,clos'],

            // 💰 Tarification (si colonnes présentes et table tarifs dispo)
            'tarif_id'      => $hasTarifs ? ['sometimes','nullable','uuid','exists:tarifs,id'] : ['prohibited'],
            'remise_pct'    => ['sometimes','nullable','numeric','min:0','max:100'],
            'exempt'        => ['sometimes','boolean'],
            'motif_gratuite'=> ['sometimes','nullable','string','max:150'],

            // (option) si tu gères explicitement l’affectation via l’API update :
            // 'affectation_id' => ['sometimes','nullable','uuid','exists:affectations,id'],
        ];
    }
}
