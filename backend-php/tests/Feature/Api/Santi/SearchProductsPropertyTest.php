<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Category;
use App\Models\Product;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\Tools\SearchProductsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SearchProductsPropertyTest extends TestCase
{
    use RefreshDatabase;
    use WithSantiGenerators;

    /**
     * Property 11: Search filters are conjunctive and results are bounded.
     *
     * **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5, 3.7, 3.8, 3.9**
     */
    public function test_search_filters_are_conjunctive_and_results_are_bounded(): void
    {
        $tool = new SearchProductsTool();

        $this->runSantiProperty(function (int $iteration, int $seed) use ($tool): void {
            $searchTerm = "santi-search-{$seed}-{$iteration}";
            $category = Category::factory()->create([
                'name' => "Santi {$searchTerm} category",
                'slug' => "santi-search-category-{$seed}-{$iteration}",
            ]);
            $otherCategory = Category::factory()->create([
                'name' => "Santi other category {$seed}-{$iteration}",
                'slug' => "santi-other-category-{$seed}-{$iteration}",
            ]);
            $minimumPrice = mt_rand(1_000, 50_000);
            $maximumPrice = $minimumPrice + mt_rand(1, 50_000);
            $matchingCount = $iteration % 26;
            $catalog = collect();

            for ($index = 0; $index < $matchingCount; $index++) {
                $catalog->push(Product::factory()->create([
                    'category_id' => $category->id,
                    // The first matching product proves that category-name matches are included.
                    'name' => $index === 0
                        ? "Santi category-only product {$seed}-{$iteration}"
                        : "Santi {$searchTerm} product {$index}",
                    'slug' => "santi-search-match-{$seed}-{$iteration}-{$index}",
                    'price' => mt_rand($minimumPrice, $maximumPrice),
                    'stock' => mt_rand(0, 100),
                    'is_active' => true,
                ]));
            }

            $nearMisses = collect([
                Product::factory()->create([
                    'category_id' => $otherCategory->id,
                    'name' => "Santi {$searchTerm} wrong category",
                    'slug' => "santi-search-wrong-category-{$seed}-{$iteration}",
                    'price' => mt_rand($minimumPrice, $maximumPrice),
                    'is_active' => true,
                ]),
                Product::factory()->create([
                    'category_id' => $category->id,
                    'name' => "Santi low price {$seed}-{$iteration}",
                    'slug' => "santi-search-low-price-{$seed}-{$iteration}",
                    'price' => $minimumPrice - 1,
                    'is_active' => true,
                ]),
                Product::factory()->create([
                    'category_id' => $category->id,
                    'name' => "Santi high price {$seed}-{$iteration}",
                    'slug' => "santi-search-high-price-{$seed}-{$iteration}",
                    'price' => $maximumPrice + 1,
                    'is_active' => true,
                ]),
                Product::factory()->create([
                    'category_id' => $category->id,
                    'name' => "Santi inactive {$searchTerm}",
                    'slug' => "santi-search-inactive-{$seed}-{$iteration}",
                    'price' => mt_rand($minimumPrice, $maximumPrice),
                    'is_active' => false,
                ]),
            ]);
            $catalog = $catalog->concat($nearMisses);
            $categoryNames = [
                (int) $category->id => $category->name,
                (int) $otherCategory->id => $otherCategory->name,
            ];

            $expectedMatches = $catalog
                ->filter(function (Product $product) use ($searchTerm, $category, $categoryNames, $minimumPrice, $maximumPrice): bool {
                    $categoryName = $categoryNames[(int) $product->category_id];
                    $matchesQuery = str_contains(strtolower($product->name), strtolower($searchTerm))
                        || str_contains(strtolower($categoryName), strtolower($searchTerm));

                    return $product->is_active
                        && $matchesQuery
                        && $categoryName === $category->name
                        && (int) $product->price >= $minimumPrice
                        && (int) $product->price <= $maximumPrice;
                })
                ->sortBy('id')
                ->values();

            $context = new ToolContext(
                user: null,
                correlationId: "search-products-property-{$seed}-{$iteration}",
                draftRequestId: "search-products-draft-{$seed}",
            );
            $response = $tool->handle([
                'query' => $searchTerm,
                'category' => $category->name,
                'min_price' => $minimumPrice,
                'max_price' => $maximumPrice,
            ], $context)->toFunctionResponse($tool->responseSchema());

            $results = $response['data']['results'];
            $expectedReturned = $expectedMatches->take(20)->values();

            $this->assertSame(true, $response['ok']);
            $this->assertLessThanOrEqual(20, count($results));
            $this->assertSame($expectedReturned->count(), $response['data']['returned_count']);
            $this->assertSame($expectedMatches->count() > 20, $response['data']['has_more']);
            $this->assertSame(
                $expectedReturned->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
                array_column($results, 'id'),
            );
            $this->assertEmpty(array_intersect(
                $nearMisses->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
                array_column($results, 'id'),
            ));

            foreach ($results as $result) {
                $this->assertSame(true, $result['is_active']);
                $this->assertSame($category->name, $result['category']);
                $this->assertGreaterThanOrEqual($minimumPrice, $result['price']);
                $this->assertLessThanOrEqual($maximumPrice, $result['price']);
                $this->assertTrue(
                    str_contains(strtolower($result['name']), strtolower($searchTerm))
                    || str_contains(strtolower($result['category']), strtolower($searchTerm)),
                );
            }

            if ($expectedMatches->count() === 21) {
                $this->assertCount(20, $results);
                $this->assertSame(true, $response['data']['has_more']);
            }
        });
    }
}
