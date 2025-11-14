<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = $request->user();
        $isSuper = $user && ($user->hasRole('superuser') || $user->hasRole('admin'));

        return [
            'id'        => (int) $this->id,
            'slug'      => $this->slug,
            'name'      => $this->name,
            'code'      => $this->code,
            'is_active' => (bool) $this->is_active,

            // Optionnel: exposer la config (non sensible)
            'config'    => $this->when($isSuper, $this->config),

            // Webhooks (sensibles) => seulement superuser/admin
            'webhook_url'     => $this->when($isSuper, $this->webhook_url),
            'webhook_method'  => $this->when($isSuper, $this->webhook_method),
            'webhook_event'   => $this->when($isSuper, $this->webhook_event),
            'webhook_enabled' => $this->when($isSuper, (bool) $this->webhook_enabled),
            'webhook_token'   => $this->when($isSuper, $this->webhook_token),
            'webhook_secret'  => $this->when($isSuper, $this->webhook_secret),

            // Tarif courant si relation chargée
            'tarif' => $this->whenLoaded('tarif', function () {
                return $this->tarif ? [
                    'id'    => $this->tarif->id,
                    'label' => $this->tarif->label ?? null,
                    'price' => $this->tarif->price ?? null,
                    'code'  => $this->tarif->code ?? null,
                ] : null;
            }),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
