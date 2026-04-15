<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'compare_price',
        'stock',
        'sku',
        'compatibility',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
        'rating_avg'  => 'float',
    ];

    // -------------------------------------------------------
    // Auto-genera el slug desde el name al crear/actualizar
    // -------------------------------------------------------
    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });

        static::updating(function (Product $product) {
            if ($product->isDirty('name') && empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    // -------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------

    /** Categoría a la que pertenece */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Todas las imágenes del producto */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /** La imagen principal (is_primary = true) */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->whereRaw('is_primary = true');
    }

    /** Reseñas de este producto */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** Ítems de órdenes donde aparece este producto */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    /** ¿Hay unidades disponibles? */
    public function isAvailable(): bool
    {
        return $this->is_active && $this->stock > 0;
    }

    /** URL de la imagen principal o null */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        return $this->primaryImage?->image_url;
    }

    /**
     * Recalcula rating_avg y reviews_count desde la tabla reviews.
     * Llamado automáticamente al crear / borrar una reseña.
     */
    public function recalculateRating(): void
    {
        $this->update([
            'rating_avg'    => $this->reviews()->whereRaw('is_approved = true')->avg('rating') ?? 0,
            'reviews_count' => $this->reviews()->whereRaw('is_approved = true')->count(),
        ]);
    }
}
