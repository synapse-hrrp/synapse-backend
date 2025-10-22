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
            'id'           => $this->id,
            'personnel_id' => $this->personnel_id,
            'numero_ordre' => $this->numero_ordre,
            'specialite'   => $this->specialite,
            'grade'        => $this->grade,
            'display'      => $this->display, // accessor du modèle

            // Relations (renvoyées uniquement si chargées)
            'personnel' => $this->whenLoaded('personnel', function () {
                return [
                    'id'         => $this->personnel->id,
                    'full_name'  => $this->personnel->full_name,
                    'service_id' => $this->personnel->service_id,
                    'user'       => $this->whenLoaded('personnel.user', function () {
                        return [
                            'id'     => $this->personnel->user->id,
                            'name'   => $this->personnel->user->name,
                            'email'  => $this->personnel->user->email,
                            'phone'  => $this->personnel->user->phone,
                            'active' => (bool) $this->personnel->user->is_active,
                        ];
                    }),
                ];
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
