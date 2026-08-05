<?php

namespace App\Services\Chatbot;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Chatbot\Exceptions\DraftLimitException;
use App\Services\Chatbot\Exceptions\DraftUnavailableException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DraftOrderService
{
    /**
     * @param array<int, array{product_identifier: string, quantity: int}> $items
     *
     * @throws DraftLimitException
     * @throws DraftUnavailableException
     */
    public function create(User $user, array $items, string $draftRequestId): Order
    {
        if (! Str::isUuid($draftRequestId)) {
            throw new DraftLimitException(DraftLimitException::VALIDATION_ERROR);
        }

        $normalizedItems = $this->normalizeItems($items);

        $existingDraft = $this->findExistingDraft($user, $draftRequestId);
        if ($existingDraft !== null) {
            return $existingDraft;
        }

        try {
            return DB::transaction(function () use ($user, $draftRequestId, $normalizedItems): Order {
                $existingDraft = $this->findExistingDraft($user, $draftRequestId, lock: true);
                if ($existingDraft !== null) {
                    return $existingDraft;
                }

                $products = $this->lockProducts($normalizedItems);
                $itemsByProduct = $this->resolveProducts($normalizedItems, $products);
                $orderItems = $this->buildOrderItems($itemsByProduct);
                $subtotal = array_sum(array_column($orderItems, 'subtotal'));

                if ($subtotal > (int) config('santi.draft_max_subtotal_clp')) {
                    throw new DraftLimitException(DraftLimitException::SUBTOTAL_LIMIT_EXCEEDED);
                }

                $order = Order::create([
                    'user_id' => $user->id,
                    'shipping_name' => null,
                    'shipping_phone' => null,
                    'shipping_street' => null,
                    'shipping_city' => null,
                    'shipping_region' => null,
                    'shipping_method' => null,
                    'status' => 'draft',
                    'draft_request_id' => $draftRequestId,
                    'draft_expires_at' => now()->addHours((int) config('santi.draft_ttl_hours')),
                    'subtotal' => $subtotal,
                    'shipping_cost' => 0,
                    'discount' => 0,
                    'total' => $subtotal,
                    'payment_method' => null,
                    'stripe_payment_id' => null,
                    'notes' => null,
                    'paid_at' => null,
                    'shipped_at' => null,
                    'delivered_at' => null,
                ]);

                foreach ($orderItems as $item) {
                    OrderItem::create($item + ['order_id' => $order->id]);
                }

                return $order->load('items');
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $winningDraft = $this->findExistingDraft($user, $draftRequestId);
            if ($winningDraft !== null) {
                return $winningDraft;
            }

            throw $exception;
        }
    }

    /**
     * @param array<int, mixed> $items
     * @return array<string, array{identifier: string, quantity: int, numeric_id: ?int}>
     *
     * @throws DraftLimitException
     */
    private function normalizeItems(array $items): array
    {
        if ($items === []) {
            throw new DraftLimitException(DraftLimitException::VALIDATION_ERROR);
        }

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)
                || ! isset($item['product_identifier'], $item['quantity'])
                || ! is_string($item['product_identifier'])
                || ! is_int($item['quantity'])
                || trim($item['product_identifier']) === ''
                || strlen($item['product_identifier']) > 255
                || $item['quantity'] < 1) {
                throw new DraftLimitException(DraftLimitException::VALIDATION_ERROR);
            }

            $identifier = $item['product_identifier'];
            $numericId = $this->numericProductId($identifier);
            $key = ctype_digit($identifier)
                ? 'id:' . ($numericId === null ? $identifier : $numericId)
                : 'slug:' . $identifier;

            if (! isset($normalized[$key])) {
                $normalized[$key] = [
                    'identifier' => $identifier,
                    'quantity' => 0,
                    'numeric_id' => $numericId,
                ];
            }

            $normalized[$key]['quantity'] += $item['quantity'];
        }

        if (count($normalized) > 20) {
            throw new DraftLimitException(DraftLimitException::ITEM_LIMIT_EXCEEDED);
        }

        foreach ($normalized as $item) {
            if ($item['quantity'] > 99) {
                throw new DraftLimitException(DraftLimitException::VALIDATION_ERROR);
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, array{identifier: string, quantity: int, numeric_id: ?int}> $items
     */
    private function lockProducts(array $items): Collection
    {
        $productIds = [];
        $slugs = [];

        foreach ($items as $item) {
            if ($item['numeric_id'] !== null) {
                $productIds[] = $item['numeric_id'];
            } elseif (! ctype_digit($item['identifier'])) {
                $slugs[] = $item['identifier'];
            }
        }

        return Product::query()
            ->with('primaryImage')
            ->where(function ($query) use ($productIds, $slugs): void {
                if ($productIds !== []) {
                    $query->whereIn('id', array_values(array_unique($productIds)));
                }

                if ($slugs !== []) {
                    $method = $productIds === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('slug', array_values(array_unique($slugs)));
                }
            })
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param array<string, array{identifier: string, quantity: int, numeric_id: ?int}> $items
     * @return array<int, array{product: Product, quantity: int}>
     *
     * @throws DraftLimitException
     * @throws DraftUnavailableException
     */
    private function resolveProducts(array $items, Collection $products): array
    {
        $productsById = $products->keyBy('id');
        $productsBySlug = $products->keyBy('slug');
        $resolved = [];

        foreach ($items as $item) {
            $product = $item['numeric_id'] !== null
                ? $productsById->get($item['numeric_id'])
                : $productsBySlug->get($item['identifier']);

            if (! $product instanceof Product) {
                throw new DraftUnavailableException(DraftUnavailableException::PRODUCT_NOT_FOUND);
            }

            if (! isset($resolved[$product->id])) {
                $resolved[$product->id] = [
                    'product' => $product,
                    'quantity' => 0,
                ];
            }

            $resolved[$product->id]['quantity'] += $item['quantity'];
        }

        if (count($resolved) > 20) {
            throw new DraftLimitException(DraftLimitException::ITEM_LIMIT_EXCEEDED);
        }

        foreach ($resolved as $item) {
            if ($item['quantity'] > 99) {
                throw new DraftLimitException(DraftLimitException::VALIDATION_ERROR);
            }
        }

        return $resolved;
    }

    /**
     * @param array<int, array{product: Product, quantity: int}> $itemsByProduct
     * @return array<int, array{product_id: int, product_name: string, product_price: int, product_image: ?string, quantity: int, subtotal: int}>
     *
     * @throws DraftUnavailableException
     */
    private function buildOrderItems(array $itemsByProduct): array
    {
        $orderItems = [];

        foreach ($itemsByProduct as $item) {
            /** @var Product $product */
            $product = $item['product'];

            if (! $product->is_active) {
                throw new DraftUnavailableException(DraftUnavailableException::PRODUCT_UNAVAILABLE);
            }

            if ($product->stock < $item['quantity']) {
                throw new DraftUnavailableException(DraftUnavailableException::INSUFFICIENT_STOCK);
            }

            $unitPrice = (int) $product->price;
            $quantity = $item['quantity'];

            $orderItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_price' => $unitPrice,
                'product_image' => $product->primaryImage?->image_url,
                'quantity' => $quantity,
                'subtotal' => $unitPrice * $quantity,
            ];
        }

        return $orderItems;
    }

    private function findExistingDraft(User $user, string $draftRequestId, bool $lock = false): ?Order
    {
        $query = Order::query()
            ->where('user_id', $user->id)
            ->where('draft_request_id', $draftRequestId);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function numericProductId(string $identifier): ?int
    {
        if (! ctype_digit($identifier)) {
            return null;
        }

        $normalized = ltrim($identifier, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $maxInteger = (string) PHP_INT_MAX;

        if (strlen($normalized) > strlen($maxInteger)
            || (strlen($normalized) === strlen($maxInteger) && strcmp($normalized, $maxInteger) > 0)) {
            return null;
        }

        return (int) $normalized;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
