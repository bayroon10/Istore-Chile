<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\ToolExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DraftSubtotalPropertyTest extends TestCase
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
     * Property 5: Persisted draft totals derive solely from catalog prices and requested quantities.
     *
     * **Validates: Requirements 4.4, 4.6, 6.5, 7.1**
     */
    public function test_stored_subtotal_and_total_use_catalog_prices_and_quantities_not_model_amounts(): void
    {
        $executor = new ToolExecutor();
        $categoryCounter = 0;

        $this->runSantiProperty(function (int $iteration, int $seed) use ($executor, &$categoryCounter): void {
            $fixtureKey = "{$seed}-{$iteration}-{$categoryCounter}";
            $categoryCounter++;
            $category = Category::query()->create([
                'name' => "Santi subtotal category {$fixtureKey}",
                'slug' => "santi-subtotal-category-{$fixtureKey}",
                'sort_order' => $categoryCounter,
            ]);
            $items = [];
            $expectedLines = [];
            $expectedSubtotal = 0;
            $productCount = mt_rand(1, 10);

            for ($productNumber = 0; $productNumber < $productCount; $productNumber++) {
                $product = Product::query()->create([
                    'category_id' => $category->id,
                    'name' => "Santi subtotal {$fixtureKey}-{$productNumber}",
                    'slug' => "santi-subtotal-{$fixtureKey}-{$productNumber}",
                    'description' => "Deterministic property fixture {$fixtureKey}-{$productNumber}",
                    'price' => mt_rand(1_000, 5_000),
                    'stock' => 99,
                    'sku' => "SANTI-SUBTOTAL-{$fixtureKey}-{$productNumber}",
                    'is_active' => true,
                    'is_featured' => false,
                ]);
                $quantity = mt_rand(1, 99);
                $lineSubtotal = (int) $product->price * $quantity;
                $expectedSubtotal += $lineSubtotal;
                $expectedLines[(int) $product->id] = [
                    'price' => (int) $product->price,
                    'quantity' => $quantity,
                    'subtotal' => $lineSubtotal,
                ];
                $items[] = [
                    'product_identifier' => mt_rand(0, 1) === 0 ? (string) $product->id : $product->slug,
                    'quantity' => $quantity,
                ];
            }

            $modelSubtotal = mt_rand(50_000_000, 60_000_000);
            $modelTotal = mt_rand(70_000_000, 80_000_000);
            $rejectedUser = $this->santiCustomer();
            $rejectedDraftRequestId = (string) Str::uuid();
            $rejection = $executor->execute('create_draft_order', [
                'items' => $items,
                'price' => $modelSubtotal,
                'subtotal' => $modelSubtotal,
                'total' => $modelTotal,
            ], new ToolContext(
                user: $rejectedUser,
                correlationId: "draft-subtotal-rejection-{$fixtureKey}",
                draftRequestId: $rejectedDraftRequestId,
            ))->toFunctionResponse();

            $this->assertFalse($rejection['ok']);
            $this->assertSame('VALIDATION_ERROR', $rejection['error_code']);
            $this->assertDatabaseMissing('orders', [
                'user_id' => $rejectedUser->id,
                'draft_request_id' => $rejectedDraftRequestId,
            ]);

            $user = $this->santiCustomer();
            $draftRequestId = (string) Str::uuid();
            $response = $executor->execute('create_draft_order', ['items' => $items], new ToolContext(
                user: $user,
                correlationId: "draft-subtotal-property-{$fixtureKey}",
                draftRequestId: $draftRequestId,
            ))->toFunctionResponse();

            $this->assertTrue($response['ok']);

            $order = Order::query()->with('items')
                ->where('user_id', $user->id)
                ->where('draft_request_id', $draftRequestId)
                ->sole();

            $this->assertSame($expectedSubtotal, (int) $order->subtotal);
            $this->assertSame($expectedSubtotal, (int) $order->total);
            $this->assertCount($productCount, $order->items);

            foreach ($order->items as $orderItem) {
                $expected = $expectedLines[(int) $orderItem->product_id];

                $this->assertSame($expected['price'], (int) $orderItem->product_price);
                $this->assertSame($expected['quantity'], (int) $orderItem->quantity);
                $this->assertSame($expected['subtotal'], (int) $orderItem->subtotal);
            }
        });
    }
}
