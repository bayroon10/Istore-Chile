<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        \Illuminate\Support\Facades\Log::info('[OBSERVABILITY-CATEGORY-RESOURCE] Iniciando toArray()', [
            'category_id' => $this->id,
            'created_at_raw' => $this->created_at,
        ]);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => (bool) $this->is_active,
            'products_count' => $this->whenCounted('products'),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
