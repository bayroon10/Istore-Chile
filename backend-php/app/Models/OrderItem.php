<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_price',
        'product_image',
        'quantity',
        'subtotal',
        'fulfillment_status',
    ];

    // -------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Producto actual (puede ser null si fue borrado).
     * Siempre usar product_name/product_price para mostrar en historial.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withDefault([
            'name'  => $this->product_name,
            'price' => $this->product_price,
        ]);
    }
}
