<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExamenResource extends JsonResource
{
    private function pick($model, array $keys)
    {
        foreach ($keys as $k) {
            if (isset($model->{$k}) && $model->{$k} !== '') {
                return $model->{$k};
            }
        }
        return null;
    }

    public function toArray($request)
    {
        // Prépare noms/prénoms demandeur/validateur de façon robuste
        $demandeurPersonnel = ($this->relationLoaded('demandeur') && $this->demandeur && $this->demandeur->relationLoaded('personnel'))
            ? $this->demandeur->personnel
            : null;

        $validateurPerso = $this->relationLoaded('validateur') ? $this->validateur : null;

        $dem_nom    = $demandeurPersonnel ? $this->pick($demandeurPersonnel, ['nom','last_name','surname','family_name','name']) : null;
        $dem_prenom = $demandeurPersonnel ? $this->pick($demandeurPersonnel, ['prenom','first_name','given_name']) : null;
        $dem_full   = trim(($dem_nom ?? '').' '.($dem_prenom ?? ''));

        $val_nom    = $validateurPerso ? $this->pick($validateurPerso, ['nom','last_name','surname','family_name','name']) : null;
        $val_prenom = $validateurPerso ? $this->pick($validateurPerso, ['prenom','first_name','given_name']) : null;
        $val_full   = trim(($val_nom ?? '').' '.($val_prenom ?? ''));

        return [
            // Ancrages
            'id'             => $this->id,
            'patient_id'     => $this->patient_id,
            'service_slug'   => $this->service_slug,

            // Origine
            'created_via'    => $this->created_via,
            'type_origine'   => $this->type_origine,

            // Examen / tarification
            'code_examen'    => $this->code_examen,
            'nom_examen'     => $this->nom_examen,
            'prix'           => $this->prix,
            'devise'         => $this->devise,

            // Statuts / dates
            'statut'          => $this->statut,
            'date_demande'    => optional($this->date_demande)->toISOString(),
            'date_validation' => optional($this->date_validation)->toISOString(),

            // Facturation (plats + résumé facture)
            'facture_id'      => $this->facture_id,
            'facture_numero'  => $this->when($this->relationLoaded('facture'), optional($this->facture)->numero),

            // Timestamps
            'created_at'      => optional($this->created_at)->toISOString(),
            'updated_at'      => optional($this->updated_at)->toISOString(),

            // Relations
            'patient' => $this->whenLoaded('patient', fn () => [
                'id'             => $this->patient->id,
                'nom'            => $this->pick($this->patient, ['nom','last_name','surname','family_name','name']),
                'prenom'         => $this->pick($this->patient, ['prenom','first_name','given_name']),
                'numero_dossier' => $this->patient->numero_dossier ?? null,
            ]),

            'service' => $this->whenLoaded('service', fn () => [
                'slug' => $this->service->slug,
                'name' => $this->service->name ?? null,
            ]),

            'demandeur' => $this->whenLoaded('demandeur', fn () => [
                'id'           => $this->demandeur->id,
                'personnel_id' => $this->demandeur->personnel_id,
                'nom'          => $dem_nom,
                'prenom'       => $dem_prenom,
                'full_name'    => $dem_full !== '' ? $dem_full : ($this->demandeur->full_name ?? null),
            ]),

            'validateur' => $this->whenLoaded('validateur', fn () => [
                'id'        => $this->validateur->id,
                'nom'       => $val_nom,
                'prenom'    => $val_prenom,
                'full_name' => $val_full !== '' ? $val_full : ($this->validateur->full_name ?? null),
            ]),

            // 🔎 Détail facture complet (affichera bien statut/montants/date)
            'facture' => $this->whenLoaded('facture', fn () => [
                'id'            => $this->facture->id,
                'numero'        => $this->facture->numero,
                'statut'        => $this->facture->statut,
                'montant_total' => $this->facture->montant_total,
                'montant_du'    => $this->facture->montant_du,
                'devise'        => $this->facture->devise,
                'created_at'    => optional($this->facture->created_at)->toISOString(),
            ]),
        ];
    }
}
