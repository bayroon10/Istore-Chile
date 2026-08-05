<?php

namespace App\Services\Chatbot\Tools;

use App\Models\Product;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\ToolContract;
use App\Services\Chatbot\ToolResult;

final class SearchProductsTool implements ToolContract
{
    private const RETURN_LIMIT = 20;

    private const FETCH_LIMIT = self::RETURN_LIMIT + 1;

    public function name(): string
    {
        return 'search_products';
    }

    /** @return array<string, mixed> */
    public function declaration(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Busca productos activos del catálogo por texto y filtros opcionales. Úsala para recomendar productos.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Texto a buscar en nombre de producto y nombre de categoría. Entre 1 y 100 caracteres.',
                    ],
                    'category' => [
                        'type' => 'string',
                        'description' => 'Nombre exacto de categoría para filtrar.',
                    ],
                    'min_price' => [
                        'type' => 'integer',
                        'description' => 'Precio mínimo en pesos chilenos, entero sin decimales.',
                    ],
                    'max_price' => [
                        'type' => 'integer',
                        'description' => 'Precio máximo en pesos chilenos, entero sin decimales.',
                    ],
                ],
                'required' => ['query'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'results' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'slug' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                            'category' => ['type' => ['string', 'null']],
                            'price' => ['type' => 'integer'],
                            'stock' => ['type' => 'integer'],
                            'is_active' => ['type' => 'boolean'],
                        ],
                    ],
                ],
                'returned_count' => ['type' => 'integer'],
                'has_more' => ['type' => 'boolean'],
            ],
        ];
    }

    /** @return array<string, string|array> */
    public function rules(): array
    {
        return [
            'query' => 'required|string|min:1|max:100',
            'category' => 'nullable|string|max:100',
            'min_price' => 'nullable|integer|min:0|max:100000000',
            'max_price' => 'nullable|integer|min:0|max:100000000',
        ];
    }

    public function requiresAuth(): bool
    {
        return false;
    }

    /**
     * @param array{query: string, category?: string|null, min_price?: int|null, max_price?: int|null} $args
     */
    public function handle(array $args, ToolContext $ctx): ToolResult
    {
        $query = trim($args['query']);
        $minPrice = $args['min_price'] ?? null;
        $maxPrice = $args['max_price'] ?? null;

        if ($query === '') {
            return ToolResult::error('VALIDATION_ERROR', 'La búsqueda debe incluir texto.');
        }

        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            return ToolResult::error('INVALID_PRICE_RANGE', 'El precio mínimo no puede superar el precio máximo.');
        }

        $pattern = '%'.strtolower($query).'%';

        $products = Product::query()
            ->select(['id', 'category_id', 'slug', 'name', 'price', 'stock', 'is_active'])
            ->with('category:id,name')
            ->where('is_active', true)
            ->where(function ($productQuery) use ($pattern): void {
                $productQuery
                    ->whereRaw('LOWER(name) LIKE ?', [$pattern])
                    ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$pattern]));
            })
            ->when(
                $args['category'] ?? null,
                fn ($productQuery, string $category) => $productQuery->whereHas(
                    'category',
                    fn ($categoryQuery) => $categoryQuery->where('name', $category),
                ),
            )
            ->when($minPrice !== null, fn ($productQuery) => $productQuery->where('price', '>=', $minPrice))
            ->when($maxPrice !== null, fn ($productQuery) => $productQuery->where('price', '<=', $maxPrice))
            ->orderBy('id')
            ->limit(self::FETCH_LIMIT)
            ->get();

        $hasMore = $products->count() > self::RETURN_LIMIT;
        $results = $products
            ->take(self::RETURN_LIMIT)
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'category' => $product->category?->name,
                'price' => (int) $product->price,
                'stock' => (int) $product->stock,
                'is_active' => (bool) $product->is_active,
            ])
            ->values()
            ->all();

        return ToolResult::ok([
            'results' => $results,
            'returned_count' => count($results),
            'has_more' => $hasMore,
        ]);
    }
}
