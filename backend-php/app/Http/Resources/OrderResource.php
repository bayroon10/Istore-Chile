<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'order_number'      => $this->order_number,
            'status'            => $this->status,
            'status_label'      => $this->status_label,    // accessor: "Pagado", "Enviado", etc.

            // Envío
            'shipping' => [
                'name'   => $this->shipping_name,
                'phone'  => $this->shipping_phone,
                'street' => $this->shipping_street,
                'city'   => $this->shipping_city,
                'region' => $this->shipping_region,
                'method' => $this->shipping_method,
            ],

            // Montos
            'subtotal'      => $this->subtotal,
            'shipping_cost' => $this->shipping_cost,
            'discount'      => $this->discount,
            'total'         => $this->total,

            // Pago
            'payment_method'    => $this->payment_method,
            'stripe_payment_id' => $this->when($request->user()?->isAdmin(), $this->stripe_payment_id),

            // Items
            'items'       => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenLoaded('items', fn () => $this->items->count()),

            // Cliente (solo visible para admin)
            'customer' => $this->when($request->user()?->isAdmin() && $this->relationLoaded('user'), [
                'id'    => $this->user?->id,
                'name'  => $this->user?->name,
                'email' => $this->user?->email,
            ]),

            // Notas
            'notes' => $this->notes,

            // Timestamps
            'paid_at'      => $this->paid_at?->toISOString(),
            'shipped_at'   => $this->shipped_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'created_at'   => $this->created_at->toISOString(),
        ];
    }
}
