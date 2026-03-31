<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'parent_id',
        'sort_order',
        'is_active',
    ];

    // -------------------------------------------------------
    // Auto-genera el slug desde el name al crear/actualizar
    // -------------------------------------------------------
    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::updating(function (Category $category) {
            if ($category->isDirty('name') && empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    // -------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------

    /** Subcategorías (jerarquía) */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /** Categoría padre */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** Productos que pertenecen a esta categoría */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
