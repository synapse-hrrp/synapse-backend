<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'slug'      => $this->slug,
            'name'      => $this->name,
            'code'      => $this->code,
            'is_active' => (bool) $this->is_active,

            // nouvelles colonnes webhook
            'webhook_url'     => $this->when($request->user()?->tokenCan('*'), $this->webhook_url),
            'webhook_method'  => $this->when($request->user()?->tokenCan('*'), $this->webhook_method),
            'webhook_event'   => $this->when($request->user()?->tokenCan('*'), $this->webhook_event),
            'webhook_enabled' => (bool) $this->webhook_enabled,

            // ⚠️ sensibles : on ne les expose qu’aux super-admins (éviter fuite)
            'webhook_token'   => $this->when($request->user()?->tokenCan('*'), $this->webhook_token),
            'webhook_secret'  => $this->when($request->user()?->tokenCan('*'), $this->webhook_secret),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
