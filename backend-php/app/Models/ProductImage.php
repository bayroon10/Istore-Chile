<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'url',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    // -------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------

    /** Producto al que pertenece esta imagen */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
