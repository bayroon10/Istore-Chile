<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
    ];

    // -------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    /** Total de ítems (suma de cantidades) */
    public function getTotalItemsAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    /** Total en pesos */
    public function getTotalPriceAttribute(): int
    {
        return $this->items->sum(fn ($item) => $item->product->price * $item->quantity);
    }
}
