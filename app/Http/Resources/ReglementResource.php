<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReglementResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'montant'    => (string) $this->montant,
            'mode'       => $this->mode,
            'reference'  => $this->reference,
            'devise'     => $this->devise,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
