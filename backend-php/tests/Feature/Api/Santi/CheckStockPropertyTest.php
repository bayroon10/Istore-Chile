<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Product;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\Tools\CheckStockTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CheckStockPropertyTest extends TestCase
{
    use RefreshDatabase;
    use WithSantiGenerators;

    /**
     * Property 12: check_stock reflects current catalog data and distinguishes not found from no stock.
     *
     * **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.6**
     */
    public function test_check_stock_reflects_catalog_state_and_distinguishes_not_found_from_zero_stock(): void
    {
        $tool = new CheckStockTool();

        $this->runSantiProperty(function (int $iteration, int $seed) use ($tool): void {
            $products = [
                Product::factory()->create([
                    'name' => "Santi active stock {$iteration}",
                    'slug' => "santi-active-stock-{$iteration}",
                    'is_active' => true,
                    'stock' => mt_rand(1, 100),
                ]),
                Product::factory()->create([
                    'name' => "Santi zero stock {$iteration}",
                    'slug' => "santi-zero-stock-{$iteration}",
                    'is_active' => true,
                    'stock' => 0,
                ]),
                Product::factory()->create([
                    'name' => "Santi inactive stock {$iteration}",
                    'slug' => "santi-inactive-stock-{$iteration}",
                    'is_active' => false,
                    'stock' => mt_rand(1, 100),
                ]),
            ];

            $context = new ToolContext(
                user: null,
                correlationId: "check-stock-property-{$seed}-{$iteration}",
                draftRequestId: "check-stock-draft-{$seed}",
            );

            foreach ($products as $product) {
                foreach ([(string) $product->id, $product->slug] as $identifier) {
                    $result = $tool->handle(['product_identifier' => $identifier], $context)
                        ->toFunctionResponse($tool->responseSchema());

                    $this->assertSame([
                        'ok' => true,
                        'data' => [
                            'id' => (int) $product->id,
                            'slug' => $product->slug,
                            'name' => $product->name,
                            'is_active' => (bool) $product->is_active,
                            'stock' => (int) $product->stock,
                        ],
                    ], $result, "Identifier {$identifier} did not return current catalog data.");
                }
            }

            $missingResult = $tool->handle([
                'product_identifier' => "santi-missing-stock-{$seed}-{$iteration}",
            ], $context)->toFunctionResponse($tool->responseSchema());

            $this->assertSame(false, $missingResult['ok']);
            $this->assertSame('PRODUCT_NOT_FOUND', $missingResult['error_code']);
            $this->assertArrayNotHasKey('data', $missingResult);
        });
    }
}
