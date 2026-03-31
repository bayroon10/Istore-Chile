<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'total_items' => $this->total_items,    // accessor del modelo
            'total_price' => $this->total_price,    // accessor del modelo
            'items'       => CartItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
