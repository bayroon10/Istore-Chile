<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Chatbot\DraftOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class DraftAtomicityPropertyTest extends TestCase
{
    use RefreshDatabase;
    use WithSantiGenerators;

    /**
     * Keep this property test isolated from any database URL loaded by .env.testing.
     */
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
     * Property 6: Draft persistence is atomic when an order item cannot be persisted.
     *
     * **Validates: Requirements 4.9, 4.10, 4.17**
     */
    public function test_second_order_item_failure_rolls_back_every_draft_record(): void
    {
        $service = new DraftOrderService();

        $this->runSantiProperty(function (int $iteration, int $seed) use ($service): void {
            $fixtureKey = "{$seed}-{$iteration}";
            $category = Category::query()->create([
                'name' => "Santi atomicity category {$fixtureKey}",
                'slug' => "santi-atomicity-category-{$fixtureKey}",
                'icon' => '🧪',
                'sort_order' => $iteration,
            ]);
            $items = [];

            for ($productNumber = 1; $productNumber <= 2; $productNumber++) {
                $product = Product::query()->create([
                    'category_id' => $category->id,
                    'name' => "Santi atomicity {$fixtureKey}-{$productNumber}",
                    'slug' => "santi-atomicity-{$fixtureKey}-{$productNumber}",
                    'description' => "Deterministic atomicity fixture {$fixtureKey}-{$productNumber}",
                    'price' => mt_rand(1_000, 10_000),
                    'stock' => 99,
                    'sku' => "SANTI-ATOMICITY-{$fixtureKey}-{$productNumber}",
                    'is_active' => true,
                    'is_featured' => false,
                ]);
                $items[] = [
                    'product_identifier' => (string) $product->id,
                    'quantity' => mt_rand(1, 10),
                ];
            }

            $exceptionMessage = "Forced second OrderItem failure for {$fixtureKey}";
            $creationAttempts = 0;
            $event = 'eloquent.creating: ' . OrderItem::class;

            /** @var \Illuminate\Events\Dispatcher $dispatcher */
            $dispatcher = app('events');
            $existingListeners = $dispatcher->getRawListeners()[$event] ?? [];

            $dispatcher->listen(
                $event,
                function (OrderItem $orderItem) use (&$creationAttempts, $exceptionMessage): void {
                    $creationAttempts++;

                    if ($creationAttempts === 2) {
                        throw new RuntimeException($exceptionMessage);
                    }
                },
            );

            try {
                $service->create(
                    user: $this->santiCustomer(),
                    items: $items,
                    draftRequestId: sprintf('00000000-0000-4000-8000-%012d', $iteration + 1),
                );
                $this->fail('The forced second OrderItem persistence failure was not thrown.');
            } catch (RuntimeException $exception) {
                $this->assertSame($exceptionMessage, $exception->getMessage());
            } finally {
                $dispatcher->forget($event);

                foreach ($existingListeners as $listener) {
                    $dispatcher->listen($event, $listener);
                }
            }

            $this->assertSame(2, $creationAttempts);
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_items', 0);
        }, seed: 60_512_026, iterations: 100);
    }
}
