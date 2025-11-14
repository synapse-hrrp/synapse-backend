<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\PatientResource;

class RendezVousResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => (string) $this->id,
            'date'       => optional($this->date)->toDateString(),
            'start_time' => $this->start_time ? substr((string)$this->start_time, 0, 5) : null,
            'end_time'   => $this->end_time ? substr((string)$this->end_time, 0, 5) : null,
            'status'     => $this->status,
            'source'     => $this->source,
            'notes'      => $this->notes,

            'medecin' => $this->whenLoaded('medecin', function () {
                $m = $this->medecin;
                return $m ? [
                    'id'         => (int)$m->id,
                    'display'    => $m->display,
                    'specialite' => $m->specialite,
                    'grade'      => $m->grade,
                ] : null;
            }),

            'service' => $this->whenLoaded('service', function () {
                $s = $this->service;
                return $s ? [
                    'slug' => $s->slug,
                    'name' => $s->name,
                ] : null;
            }),

            // ✅ plus aucun warning IDE : pas de closure, pas d’accès direct
            'patient' => PatientResource::make($this->whenLoaded('patient')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
