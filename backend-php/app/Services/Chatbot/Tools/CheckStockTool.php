<?php

namespace App\Services\Chatbot\Tools;

use App\Models\Product;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\ToolContract;
use App\Services\Chatbot\ToolResult;

final class CheckStockTool implements ToolContract
{
    public function name(): string
    {
        return 'check_stock';
    }

    /** @return array<string, mixed> */
    public function declaration(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Consulta el stock real y actual de un producto específico en iStore Chile.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'product_identifier' => [
                        'type' => 'string',
                        'description' => 'ID numérico del producto o su slug exacto.',
                    ],
                ],
                'required' => ['product_identifier'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'slug' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'is_active' => ['type' => 'boolean'],
                'stock' => ['type' => 'integer'],
            ],
        ];
    }

    /** @return array<string, string|array> */
    public function rules(): array
    {
        return [
            'product_identifier' => 'required|string|max:255',
        ];
    }

    public function requiresAuth(): bool
    {
        return false;
    }

    /** @param array{product_identifier: string} $args */
    public function handle(array $args, ToolContext $ctx): ToolResult
    {
        $identifier = $args['product_identifier'];

        $product = Product::query()
            ->select(['id', 'slug', 'name', 'is_active', 'stock'])
            ->when(
                is_numeric($identifier),
                fn ($query) => $query->where('id', $identifier),
                fn ($query) => $query->where('slug', $identifier),
            )
            ->first();

        if ($product === null) {
            return ToolResult::error('PRODUCT_NOT_FOUND', 'Producto no encontrado.');
        }

        return ToolResult::ok([
            'id' => (int) $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'is_active' => (bool) $product->is_active,
            'stock' => (int) $product->stock,
        ]);
    }
}
