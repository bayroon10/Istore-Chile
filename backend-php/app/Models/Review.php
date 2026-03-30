<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'order_id',
        'rating',
        'comment',
        'is_approved',
    ];

    protected $casts = [
        'rating'      => 'integer',
        'is_approved' => 'boolean',
    ];

    // -------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // -------------------------------------------------------
    // Boot: después de crear/borrar una reseña, recalcular
    //       el rating_avg y reviews_count del producto
    // -------------------------------------------------------
    protected static function booted(): void
    {
        static::created(function (Review $review) {
            $review->product->recalculateRating();
        });

        static::deleted(function (Review $review) {
            $review->product->recalculateRating();
        });
    }
}
