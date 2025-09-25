<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PansementResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'patient_id'        => $this->patient_id,
            'visite_id'         => $this->visite_id,

            // si ta table pansements a service_id (recommandé)
            'service_id'        => $this->whenHas('service_id', $this->service_id),

            // soignant = Personnel (pas User)
            'soignant_id'       => $this->soignant_id,
            'soignant_name'     => optional($this->soignant)->full_name
                                   ?? trim((optional($this->soignant)->first_name).' '.(optional($this->soignant)->last_name)),

            'type'              => $this->type,
            'date_soin'         => optional($this->date_soin)->toISOString(), // ou ->format('Y-m-d') si tu préfères
            'status'            => $this->status,
            'observation'       => $this->observation,
            'etat_plaque'       => $this->etat_plaque,
            'produits_utilises' => $this->produits_utilises,

            // Relations (chargées avec ->with([...]) côté contrôleur si besoin)
            'patient'  => $this->whenLoaded('patient', function () {
                return [
                    'id'        => $this->patient->id,
                    'nom'       => $this->patient->nom ?? null,
                    'prenom'    => $this->patient->prenom ?? null,
                    'dossier'   => $this->patient->numero_dossier ?? null,
                ];
            }),
            'visite'   => $this->whenLoaded('visite', function () {
                return [
                    'id'           => $this->visite->id,
                    'service_id'   => $this->visite->service_id,
                    'medecin_id'   => $this->visite->medecin_id, // Personnel
                    'heure_arrivee'=> optional($this->visite->heure_arrivee)->toISOString(),
                    'statut'       => $this->visite->statut,
                ];
            }),
            // soignant = Personnel
            'soignant' => $this->whenLoaded('soignant', function () {
                return [
                    'id'         => $this->soignant->id,
                    'first_name' => $this->soignant->first_name ?? null,
                    'last_name'  => $this->soignant->last_name ?? null,
                    'full_name'  => $this->soignant->full_name ?? trim(($this->soignant->first_name ?? '').' '.($this->soignant->last_name ?? '')),
                    'job_title'  => $this->soignant->job_title ?? null,
                    'service_id' => $this->soignant->service_id ?? null,
                ];
            }),
            // si tu as ajouté la relation service() dans Pansement
            'service' => $this->whenLoaded('service', function () {
                return [
                    'id'     => $this->service->id,
                    'slug'   => $this->service->slug ?? null,
                    'name'   => $this->service->name ?? null,
                    'active' => (bool)($this->service->is_active ?? true),
                ];
            }),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
