<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Chatbot\DraftOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DraftOrderStatusPropertyTest extends TestCase
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
     * Property 1: Only draft orders are persisted through this path.
     *
     * **Validates: Requirements 4.13, 5.8, 10.5, 10.7**
     */
    public function test_draft_order_service_persists_only_unpaid_drafts_without_changing_existing_order_states(): void
    {
        $service = new DraftOrderService();
        $draftOrderIds = [];

        $this->runSantiProperty(function (int $iteration, int $seed) use ($service, &$draftOrderIds): void {
            $customer = $this->santiCustomer();
            $this->createExistingOrder($customer, ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'][$iteration % 6]);
            $this->createExistingOrder($this->santiCustomer(), 'draft');

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
            $categoryId = $createCategory()->id;

            $products = collect();

            for ($productNumber = 0; $productNumber < 4; $productNumber++) {
                $products->push(Product::create([
                    'category_id' => $categoryId,
                    'name' => "Santi draft status {$iteration}",
                    'slug' => "santi-draft-status-{$iteration}-" . mt_rand(100000, 999999),
                    'description' => "Deterministic property fixture {$seed}-{$iteration}-{$productNumber}",
                    'price' => mt_rand(1000, 10000),
                    'stock' => mt_rand(1, 99),
                    'sku' => "SANTI-DRAFT-STATUS-{$seed}-{$iteration}-{$productNumber}",
                    'is_active' => true,
                    'is_featured' => false,
                ]));
            }
            $statesBeforeCreation = $this->orderStatuses();

            $draft = $service->create(
                user: $customer,
                items: $this->santiValidItems($products),
                draftRequestId: sprintf('00000000-0000-4000-8000-%012d', $iteration + 1),
            )->refresh();
            $draftOrderIds[] = $draft->id;

            $this->assertSame('draft', $draft->status);
            $this->assertNull($draft->paid_at);
            $this->assertNull($draft->stripe_payment_id);
            $this->assertNull($draft->payment_method);
            $this->assertSame($statesBeforeCreation, $this->orderStatuses(array_keys($statesBeforeCreation)));

            foreach (Order::query()->whereIn('id', $draftOrderIds)->get() as $createdDraft) {
                $this->assertSame('draft', $createdDraft->status);
                $this->assertNull($createdDraft->paid_at);
                $this->assertNull($createdDraft->stripe_payment_id);
                $this->assertNull($createdDraft->payment_method);
            }
        }, seed: 2026051301, iterations: 100);
    }

    /**
     * @param  array<int, int>|null  $orderIds
     * @return array<int, string>
     */
    private function orderStatuses(?array $orderIds = null): array
    {
        $query = Order::query()->orderBy('id');

        if ($orderIds !== null) {
            $query->whereIn('id', $orderIds);
        }

        return $query
            ->pluck('status', 'id')
            ->all();
    }

    private function createExistingOrder(User $user, string $status): Order
    {
        $isDraft = $status === 'draft';

        return Order::create([
            'user_id' => $user->id,
            'shipping_name' => $isDraft ? null : 'Cliente Existente',
            'shipping_phone' => $isDraft ? null : '+56987654321',
            'shipping_street' => $isDraft ? null : 'Calle Existente 456',
            'shipping_city' => $isDraft ? null : 'Santiago',
            'shipping_region' => $isDraft ? null : 'Metropolitana',
            'shipping_method' => $isDraft ? null : 'Retiro',
            'status' => $status,
            'subtotal' => 10000,
            'shipping_cost' => 0,
            'discount' => 0,
            'total' => 10000,
            'payment_method' => $isDraft ? null : 'stripe',
        ]);
    }
}
