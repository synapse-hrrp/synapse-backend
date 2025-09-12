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
            'created_at'=> $this->created_at?->toISOString(),
            'updated_at'=> $this->updated_at?->toISOString(),
        ];
    }
}
