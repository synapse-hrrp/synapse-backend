<?php

namespace App\Http\Resources\Pharma;

use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray($request)
    {
        $stock = (int) ($this->stock_on_hand ?? 0);

        return [
            'id'         => (int) $this->id,
            'code'       => (string) ($this->code ?? ''),
            'name'       => (string) ($this->name ?? ''),

            // 👇 Ajout de l'alias
            'image_url'  => $this->image_url,   // URL publique OU data: SVG
            'image'      => $this->image_url,   // alias pour table qui attend "image"

            'form'       => $this->form,
            'dosage'     => $this->dosage,
            'unit'       => $this->unit,
            'brand'      => null,

            'dci'        => $this->whenLoaded('dci', function () {
                return [
                    'id'   => (int) $this->dci->id,
                    'name' => (string) $this->dci->name,
                ];
            }, null),

            'stock'      => $stock,
            'sell_price' => $this->sell_price,
            'tax_rate'   => $this->tax_rate,
        ];
    }
}
