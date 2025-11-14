<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MedecinResource extends JsonResource
{
    /**
     * @mixin \App\Models\Medecin
     */
    public function toArray($request): array
    {
        return [
            'id'           => (int) $this->id,
            'personnel_id' => (int) $this->personnel_id,
            'numero_ordre' => $this->numero_ordre,
            'specialite'   => $this->specialite,
            'grade'        => $this->grade,
            'display'      => $this->display, // accessor du modèle

            // ── Relations ─────────────────────────────────────────────
            'personnel' => $this->whenLoaded('personnel', function () {
                $p = $this->personnel;
                return $p ? [
                    'id'         => (int) $p->id,
                    'full_name'  => $p->full_name,
                    'service_id' => $p->service_id,
                    'job_title'  => $p->job_title,
                    'user'       => $p->relationLoaded('user') && $p->user ? [
                        'id'     => (int) $p->user->id,
                        'name'   => $p->user->name,
                        'email'  => $p->user->email,
                        'phone'  => $p->user->phone,
                        'active' => (bool) $p->user->is_active,
                    ] : null,
                ] : null;
            }),

            // Services liés (avec les infos pivot si la relation est chargée)
            'services' => $this->whenLoaded('services', function () {
                return $this->services->map(function ($service) {
                    return [
                        'slug'  => $service->slug,
                        'name'  => $service->name,
                        'pivot' => [
                            'is_active'         => (bool) $service->pivot->is_active,
                            'slot_duration'     => $service->pivot->slot_duration,
                            'capacity_per_slot' => $service->pivot->capacity_per_slot,
                        ],
                    ];
                })->values();
            }),

            // Planning hebdo si préchargé
            'plannings' => $this->whenLoaded('plannings', function () {
                return $this->plannings->map(fn ($p) => [
                    'weekday'           => (int) $p->weekday,
                    'start_time'        => substr($p->start_time, 0, 5),
                    'end_time'          => substr($p->end_time, 0, 5),
                    'slot_duration'     => (int) $p->slot_duration,
                    'capacity_per_slot' => (int) $p->capacity_per_slot,
                    'is_active'         => (bool) $p->is_active,
                ])->values();
            }),

            // Nombre de rendez-vous à venir (préchargé via count ou relation)
            'rendez_vous_count' => $this->when(isset($this->rendez_vous_count), $this->rendez_vous_count),

            // ── Dates ─────────────────────────────────────────────────
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
