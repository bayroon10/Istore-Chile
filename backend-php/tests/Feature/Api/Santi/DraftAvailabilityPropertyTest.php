<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Category;
use App\Models\Product;
use App\Services\Chatbot\DraftOrderService;
use App\Services\Chatbot\Exceptions\DraftUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DraftAvailabilityPropertyTest extends TestCase
{
    use RefreshDatabase;
    use WithSantiGenerators;

    /**
     * Property 8: A draft is all-or-nothing when any requested component is unavailable.
     *
     * **Validates: Requirements 4.5, 5.1**
     */
    public function test_unavailable_components_reject_the_entire_draft_without_persisting_rows(): void
    {
        $service = new DraftOrderService();

        $this->runSantiProperty(function (int $iteration, int $seed) use ($service): void {
            $customer = $this->santiCustomer();
            $categoryCounter = 0;
            $categoryIdentifier = "santi-{$seed}-{$iteration}-{$categoryCounter}";
            $categoryCounter++;
            $category = Category::query()->create([
                'name' => "Santi Property Category {$categoryIdentifier}",
                'slug' => $categoryIdentifier,
                'icon' => '🧪',
                'sort_order' => 0,
            ]);

            $available = Product::create([
                'category_id' => $category->id,
                'name' => "Santi available {$seed}-{$iteration}",
                'slug' => "santi-available-{$seed}-{$iteration}",
                'description' => "Deterministic availability fixture {$seed}-{$iteration}-available",
                'price' => mt_rand(1000, 10000),
                'stock' => mt_rand(1, 99),
                'sku' => "SANTI-AVAILABLE-{$seed}-{$iteration}",
                'is_active' => true,
                'is_featured' => false,
            ]);
            $inactive = Product::create([
                'category_id' => $category->id,
                'name' => "Santi inactive {$seed}-{$iteration}",
                'slug' => "santi-inactive-{$seed}-{$iteration}",
                'description' => "Deterministic availability fixture {$seed}-{$iteration}-inactive",
                'price' => mt_rand(1000, 10000),
                'stock' => mt_rand(1, 99),
                'sku' => "SANTI-INACTIVE-{$seed}-{$iteration}",
                'is_active' => false,
                'is_featured' => false,
            ]);
            $insufficientStock = mt_rand(0, 98);
            $insufficient = Product::create([
                'category_id' => $category->id,
                'name' => "Santi insufficient {$seed}-{$iteration}",
                'slug' => "santi-insufficient-{$seed}-{$iteration}",
                'description' => "Deterministic availability fixture {$seed}-{$iteration}-insufficient",
                'price' => mt_rand(1000, 10000),
                'stock' => $insufficientStock,
                'sku' => "SANTI-INSUFFICIENT-{$seed}-{$iteration}",
                'is_active' => true,
                'is_featured' => false,
            ]);

            $availableItem = [
                'product_identifier' => (string) $available->id,
                'quantity' => mt_rand(1, (int) $available->stock),
            ];
            $unavailableCases = [
                'missing' => [
                    'item' => ['product_identifier' => "santi-missing-{$seed}-{$iteration}", 'quantity' => 1],
                    'error' => DraftUnavailableException::PRODUCT_NOT_FOUND,
                ],
                'inactive' => [
                    'item' => ['product_identifier' => $inactive->slug, 'quantity' => 1],
                    'error' => DraftUnavailableException::PRODUCT_UNAVAILABLE,
                ],
                'insufficient-stock' => [
                    'item' => ['product_identifier' => (string) $insufficient->id, 'quantity' => $insufficientStock + 1],
                    'error' => DraftUnavailableException::INSUFFICIENT_STOCK,
                ],
            ];

            foreach ($unavailableCases as $scenario => $case) {
                try {
                    $service->create($customer, [$availableItem, $case['item']], (string) Str::uuid());
                    $this->fail("{$scenario} availability rejection unexpectedly created a draft.");
                } catch (DraftUnavailableException $exception) {
                    $this->assertSame($case['error'], $exception->errorCode(), "{$scenario} returned the wrong availability error.");
                }

                $this->assertDatabaseCount('orders', 0);
                $this->assertDatabaseCount('order_items', 0);
            }
        }, seed: 5_608_2026);
    }
}
