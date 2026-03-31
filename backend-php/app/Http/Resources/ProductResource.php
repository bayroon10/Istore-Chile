<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'compare_price' => $this->compare_price ? (float) $this->compare_price : null,
            'stock' => $this->stock,
            'is_active' => (bool) $this->is_active,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'primary_image_url' => $this->primary_image_url,
            'images' => $this->whenLoaded('images', function() {
                return $this->images->map(fn($img) => [
                    'id' => $img->id,
                    'url' => Storage::disk('public')->url($img->url),
                    'is_primary' => (bool) $img->is_primary,
                ]);
            }),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
