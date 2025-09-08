<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'invoice_id'    => $this->invoice_id,
            'montant'       => (float) $this->montant,
            'devise'        => $this->devise,
            'methode'       => $this->methode,
            'date_paiement' => optional($this->date_paiement)->toISOString(),
            'recu_par'      => $this->whenLoaded('recuPar', fn() => [
                'id'    => $this->recuPar->id,
                'name'  => $this->recuPar->name,
                'email' => $this->recuPar->email,
            ]),
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
