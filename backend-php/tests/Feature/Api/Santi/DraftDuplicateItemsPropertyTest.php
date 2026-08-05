<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Chatbot\DraftOrderService;
use App\Services\Chatbot\Exceptions\DraftLimitException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DraftDuplicateItemsPropertyTest extends TestCase
{
    use RefreshDatabase;
    use WithSantiGenerators;

    /**
     * Property 9: Duplicate identifiers consolidate before quantity and distinct-item limits.
     *
     * **Validates: Requirements 4.3, 4.8**
     */
    public function test_duplicate_identifiers_consolidate_before_quantity_and_distinct_item_limits(): void
    {
        $service = new DraftOrderService();

        $this->runSantiProperty(function (int $iteration, int $seed) use ($service): void {
            $customer = $this->santiCustomer();
            $categoryCounter = 0;
            $createCategory = function () use (&$categoryCounter, $seed, $iteration): Category {
                $identifier = "santi-{$seed}-{$iteration}-{$categoryCounter}";
                $categoryCounter++;

                return Category::query()->create([
                    'name' => "Santi Property Category {$identifier}",
                    'slug' => $identifier,
                    'icon' => '🧪',
                    'sort_order' => 0,
                ]);
            };
            $products = Product::factory()->count(20)->create([
                'category_id' => $createCategory()->id,
                'is_active' => true,
                'stock' => 99,
                'price' => 1_000,
            ])->values();
            $duplicateProduct = $products->get(mt_rand(0, $products->count() - 1));
            $duplicateQuantity = mt_rand(2, 99);
            $firstDuplicateQuantity = mt_rand(1, $duplicateQuantity - 1);
            $requestId = (string) Str::uuid();

            $items = $products->map(function (Product $product) use ($duplicateProduct, $firstDuplicateQuantity): array {
                return [
                    'product_identifier' => (string) $product->id,
                    'quantity' => $product->is($duplicateProduct) ? $firstDuplicateQuantity : 1,
                ];
            })->all();
            $items[] = [
                'product_identifier' => (string) $duplicateProduct->id,
                'quantity' => $duplicateQuantity - $firstDuplicateQuantity,
            ];

            $order = $service->create($customer, $items, $requestId);
            $expectedQuantities = $products->mapWithKeys(fn (Product $product): array => [
                (int) $product->id => $product->is($duplicateProduct) ? $duplicateQuantity : 1,
            ])->all();
            $actualQuantities = $order->items()->pluck('quantity', 'product_id')
                ->map(fn (mixed $quantity): int => (int) $quantity)
                ->all();
            ksort($expectedQuantities);
            ksort($actualQuantities);

            $this->assertCount(21, $items, "Seed {$seed}, iteration {$iteration} did not exercise raw-item consolidation.");
            $this->assertCount(20, $actualQuantities);
            $this->assertSame($expectedQuantities, $actualQuantities);
            $this->assertSame(1, Order::query()->whereKey($order->id)->count());
            $this->assertSame(20, OrderItem::query()->where('order_id', $order->id)->count());

            $ordersBeforeQuantityFailure = Order::count();
            $orderItemsBeforeQuantityFailure = OrderItem::count();
            try {
                $service->create($customer, [
                    ['product_identifier' => (string) $duplicateProduct->id, 'quantity' => 50],
                    ['product_identifier' => (string) $duplicateProduct->id, 'quantity' => 50],
                ], (string) Str::uuid());
                $this->fail('Consolidated quantities above 99 must be rejected.');
            } catch (DraftLimitException $exception) {
                $this->assertSame(DraftLimitException::VALIDATION_ERROR, $exception->errorCode());
            }
            $this->assertSame($ordersBeforeQuantityFailure, Order::count());
            $this->assertSame($orderItemsBeforeQuantityFailure, OrderItem::count());

            $extraProduct = Product::factory()->create([
                'category_id' => $createCategory()->id,
                'is_active' => true,
                'stock' => 99,
                'price' => 1_000,
            ]);
            $itemsWithTwentyOneDistinctProducts = $products->concat([$extraProduct])
                ->map(fn (Product $product): array => ['product_identifier' => (string) $product->id, 'quantity' => 1])
                ->all();
            $itemsWithTwentyOneDistinctProducts[] = [
                'product_identifier' => (string) $duplicateProduct->id,
                'quantity' => 1,
            ];

            try {
                $service->create($customer, $itemsWithTwentyOneDistinctProducts, (string) Str::uuid());
                $this->fail('More than 20 distinct products must be rejected after consolidation.');
            } catch (DraftLimitException $exception) {
                $this->assertSame(DraftLimitException::ITEM_LIMIT_EXCEEDED, $exception->errorCode());
            }
            $this->assertSame($ordersBeforeQuantityFailure, Order::count());
            $this->assertSame($orderItemsBeforeQuantityFailure, OrderItem::count());
        });
    }
}
