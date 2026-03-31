<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'product_id' => $this->product_id,
            'quantity'   => $this->quantity,
            'subtotal'   => $this->product->price * $this->quantity,

            // Datos del producto (para renderizar en el frontend sin fetch extra)
            'product' => [
                'id'            => $this->product->id,
                'name'          => $this->product->name,
                'slug'          => $this->product->slug,
                'price'         => $this->product->price,
                'compare_price' => $this->product->compare_price,
                'stock'         => $this->product->stock,
                'is_active'     => $this->product->is_active,
                'image'         => $this->product->primaryImage?->url,
                'category'      => $this->product->category?->name,
            ],
        ];
    }
}
