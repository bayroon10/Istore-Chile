<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Chatbot\DraftOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DraftIdempotencyPropertyTest extends TestCase
{
    use RefreshDatabase;
    use WithSantiGenerators;

    /** Keep this property test isolated from any database URL loaded by .env.testing. */
    public function createApplication()
    {
        $app = parent::createApplication();

        $app['config']->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
        ]);

        return $app;
    }

    /**
     * Property 4: draft_request_id is idempotent and scoped to its owner.
     *
     * **Validates: Requirements 4.14, 5.5, 8.9**
     */
    public function test_draft_request_id_is_idempotent_per_owner_without_cross_customer_reads(): void
    {
        $service = new DraftOrderService();

        $this->runSantiProperty(function (int $iteration, int $seed) use ($service): void {
            $owner = $this->santiCustomer();
            $otherOwner = $this->santiCustomer();
            $category = $this->createCategory($seed, $iteration);
            $ownerProduct = $this->createProduct($category, $seed, $iteration, 'owner');
            $otherProduct = $this->createProduct($category, $seed, $iteration, 'other');
            $inventoryBefore = $this->inventorySnapshot([$ownerProduct, $otherProduct]);
            $draftRequestId = sprintf('00000000-0000-4000-8000-%012d', $iteration + 1);

            $firstDraft = $service->create($owner, $this->itemsFor($ownerProduct), $draftRequestId);
            $firstItemIds = OrderItem::query()->where('order_id', $firstDraft->id)->pluck('product_id')->all();
            $retryDraft = $service->create($owner, $this->itemsFor($otherProduct), $draftRequestId);
            $otherDraft = $service->create($otherOwner, $this->itemsFor($otherProduct), $draftRequestId);

            $this->assertSame($firstDraft->id, $retryDraft->id);
            $this->assertSame($firstDraft->order_number, $retryDraft->order_number);
            $this->assertSame([$ownerProduct->id], $firstItemIds);
            $this->assertSame($firstItemIds, OrderItem::query()->where('order_id', $retryDraft->id)->pluck('product_id')->all());
            $this->assertSame(1, Order::query()->where('user_id', $owner->id)->where('draft_request_id', $draftRequestId)->count());
            $this->assertSame(1, OrderItem::query()->where('order_id', $firstDraft->id)->count());

            $this->assertNotSame($firstDraft->id, $otherDraft->id);
            $this->assertSame($owner->id, $firstDraft->fresh()->user_id);
            $this->assertSame($otherOwner->id, $otherDraft->fresh()->user_id);
            $this->assertSame(2, Order::query()->where('draft_request_id', $draftRequestId)->count());
            $this->assertSame([$otherProduct->id], OrderItem::query()->where('order_id', $otherDraft->id)->pluck('product_id')->all());
            $this->assertSame(2, OrderItem::query()->whereIn('order_id', [$firstDraft->id, $otherDraft->id])->count());

            $this->assertSame($inventoryBefore, $this->inventorySnapshot([$ownerProduct, $otherProduct]));
        }, seed: 2026051304, iterations: 100);
    }

    private function createCategory(int $seed, int $iteration): Category
    {
        $identifier = "santi-idempotency-{$seed}-{$iteration}";

        return Category::query()->create([
            'name' => "Santi Idempotency {$seed}-{$iteration}",
            'slug' => $identifier,
            'icon' => '🧪',
            'sort_order' => 0,
        ]);
    }

    private function createProduct(Category $category, int $seed, int $iteration, string $owner): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => "Santi Idempotency {$owner} {$seed}-{$iteration}",
            'slug' => "santi-idempotency-{$owner}-{$seed}-{$iteration}",
            'description' => "Deterministic idempotency fixture {$seed}-{$iteration}-{$owner}",
            'price' => 10000 + $iteration,
            'stock' => 99,
            'sku' => "SANTI-IDEMPOTENCY-{$owner}-{$seed}-{$iteration}",
            'is_active' => true,
            'is_featured' => false,
        ]);
    }

    /** @return list<array{product_identifier: string, quantity: int}> */
    private function itemsFor(Product $product): array
    {
        return [['product_identifier' => (string) $product->id, 'quantity' => 1]];
    }

    /**
     * @param list<Product> $products
     * @return array<int, array{stock: int, sales_count: int}>
     */
    private function inventorySnapshot(array $products): array
    {
        return collect($products)->mapWithKeys(function (Product $product): array {
            $freshProduct = $product->fresh();

            return [$product->id => [
                'stock' => (int) $freshProduct->stock,
                'sales_count' => (int) $freshProduct->sales_count,
            ]];
        })->all();
    }
}
