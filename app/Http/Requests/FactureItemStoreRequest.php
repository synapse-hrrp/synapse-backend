<?php

// app/Http/Requests/FactureItemStoreRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Models\Tarif;

class FactureItemStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $hasTarifs = Schema::hasTable('tarifs');

        return [
            // Contexte minimal pour créer/attacher une facture
            'patient_id'  => ['bail','required','uuid','exists:patients,id'],
            'facture_id'  => ['nullable','uuid','exists:factures,id'],

            // Saisie / Tarification
            'designation'       => ['nullable','string','max:255'],
            'tarif_id'      => $hasTarifs ? ['nullable','uuid','exists:tarifs,id'] : ['prohibited'],
            'tarif_code'    => $hasTarifs ? ['nullable','string','max:50']       : ['prohibited'],

            'quantite'      => ['nullable','integer','min:1'],
            'prix_unitaire' => ['nullable','numeric','min:0'], // requis si pas de tarif
            'remise'        => ['nullable','numeric','min:0'],
            'devise'        => ['nullable','string','size:3'],

            // Champs système pilotés en ctrl/service
            'created_by_user_id' => ['prohibited'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'tarif_code'    => $this->tarif_code !== null ? strtoupper(trim($this->tarif_code)) : null,
            'devise'        => $this->devise !== null ? strtoupper(trim($this->devise)) : null,
            'quantite'      => $this->quantite !== null ? (int) $this->quantite : null,
        ]);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $tarifId   = $this->input('tarif_id');
            $tarifCode = $this->input('tarif_code');
            $libelle   = $this->input('libelle');
            $pu        = $this->input('prix_unitaire');

            $hasTarif = (bool) ($tarifId || $tarifCode);
            $hasPU    = ($pu !== null && $pu !== '');

            // Exiger un tarif OU un prix unitaire manuel
            if (! $hasTarif && ! $hasPU) {
                $validator->errors()->add('prix_unitaire', "Fournissez un 'tarif_id/tarif_code' OU un 'prix_unitaire'.");
                return;
            }

            // Si tarif_code: il doit exister; on ne force pas le service ici (caisse centrale)
            if ($tarifCode && !$tarifId) {
                $count = \App\Models\Tarif::query()
                    ->actifs()
                    ->byCode($tarifCode)
                    ->count();

                if ($count === 0) {
                    $validator->errors()->add('tarif_code', "Aucun tarif actif trouvé pour le code {$tarifCode}.");
                    return;
                }
                if ($count > 1) {
                    $validator->errors()->add('tarif_code', "Plusieurs tarifs partagent ce code. Utilisez 'tarif_id'.");
                    return;
                }
            }

            // Si tout est manuel, libellé requis
            if (! $hasTarif && empty($libelle)) {
                $validator->errors()->add('designation', "La designation est requise en saisie manuelle.");
            }
        });
    }
}
