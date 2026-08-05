<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Throwable;

trait WithSantiGenerators
{
    protected const SANTI_PROPERTY_ITERATIONS = 100;

    private ?int $santiPropertySeed = null;

    /**
     * Runs a property at least 100 times and records the seed required to reproduce a failure.
     *
     * @param callable(int, int): void $property Receives the zero-based iteration and seed.
     */
    protected function runSantiProperty(callable $property, ?int $seed = null, int $iterations = self::SANTI_PROPERTY_ITERATIONS): void
    {
        if ($iterations < self::SANTI_PROPERTY_ITERATIONS) {
            throw new InvalidArgumentException(sprintf(
                'Santi property tests require at least %d iterations.',
                self::SANTI_PROPERTY_ITERATIONS,
            ));
        }

        $seed ??= random_int(1, PHP_INT_MAX);
        $this->santiPropertySeed = $seed;

        mt_srand($seed, MT_RAND_MT19937);
        fake()->seed($seed);
        fwrite(STDERR, sprintf("[Santi property seed: %d]\n", $seed));

        for ($iteration = 0; $iteration < $iterations; $iteration++) {
            try {
                $property($iteration, $seed);
            } catch (Throwable $exception) {
                fwrite(STDERR, sprintf(
                    "[Santi property failure: seed=%d, iteration=%d]\n",
                    $seed,
                    $iteration,
                ));

                throw $exception;
            }
        }
    }

    protected function santiPropertySeed(): ?int
    {
        return $this->santiPropertySeed;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return Collection<int, Product>
     */
    protected function santiCatalog(int $count, array $attributes = []): Collection
    {
        if ($count < 0) {
            throw new InvalidArgumentException('A Santi catalog cannot have a negative product count.');
        }

        if ($count === 0) {
            return new Collection();
        }

        return Product::factory()->count($count)->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function santiCustomer(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * Produces distinct, in-stock items accepted by create_draft_order.
     *
     * @param iterable<Product> $products
     * @return list<array{product_identifier: string, quantity: int}>
     */
    protected function santiValidItems(iterable $products, ?int $count = null): array
    {
        $availableProducts = collect($products)
            ->filter(fn (mixed $product): bool => $product instanceof Product
                && $product->is_active
                && $product->stock > 0)
            ->values();

        if ($availableProducts->isEmpty()) {
            throw new InvalidArgumentException('Valid Santi items require at least one active product with stock.');
        }

        $maximumCount = min(20, $availableProducts->count());
        $count ??= mt_rand(1, $maximumCount);

        if ($count < 1 || $count > $maximumCount) {
            throw new InvalidArgumentException(sprintf('Valid Santi item counts must be between 1 and %d.', $maximumCount));
        }

        return $availableProducts
            ->shuffle()
            ->take($count)
            ->map(function (Product $product): array {
                return [
                    'product_identifier' => mt_rand(0, 1) === 0
                        ? (string) $product->getKey()
                        : $product->slug,
                    'quantity' => mt_rand(1, min(99, (int) $product->stock)),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Produces an item list that violates one create_draft_order validation rule.
     *
     * @return list<array<string, mixed>|string>
     */
    protected function santiInvalidItems(?Product $product = null): array
    {
        $identifier = $product?->getKey() !== null
            ? (string) $product->getKey()
            : ($product?->slug ?? 'missing-product');

        $tooManyItems = array_fill(0, 21, [
            'product_identifier' => $identifier,
            'quantity' => 1,
        ]);

        $invalidCases = [
            [],
            [['product_identifier' => '', 'quantity' => 1]],
            [['product_identifier' => $identifier, 'quantity' => 0]],
            [['product_identifier' => $identifier, 'quantity' => 100]],
            [['product_identifier' => $identifier, 'quantity' => 1.5]],
            [['product_identifier' => str_repeat('x', 256), 'quantity' => 1]],
            $tooManyItems,
            ['not-an-item'],
        ];

        return $invalidCases[mt_rand(0, count($invalidCases) - 1)];
    }

    /**
     * Produces a hostile tool-call payload for ToolExecutor property tests.
     *
     * @return array{name: string, args: array<string, mixed>}
     */
    protected function santiHostileModelArguments(?Product $product = null): array
    {
        $identifier = $product?->getKey() !== null
            ? (string) $product->getKey()
            : ($product?->slug ?? 'missing-product');

        $hostileCases = [
            [
                'name' => 'check_stock',
                'args' => ['product_identifier' => "{$identifier}; DROP TABLE products; --"],
            ],
            [
                'name' => 'search_products',
                'args' => ['query' => '<script>alert(1)</script>'],
            ],
            [
                'name' => 'search_products',
                'args' => ['query' => 'https://attacker.invalid/catalog'],
            ],
            [
                'name' => 'create_draft_order',
                'args' => [
                    'items' => [['product_identifier' => $identifier, 'quantity' => 1]],
                    'price' => 1,
                ],
            ],
            [
                'name' => 'create_draft_order',
                'args' => [
                    'items' => [['product_identifier' => $identifier, 'quantity' => 1]],
                    'status' => 'paid',
                    'user_id' => 1,
                    'draft_request_id' => 'attacker-controlled-id',
                ],
            ],
        ];

        return $hostileCases[mt_rand(0, count($hostileCases) - 1)];
    }
}
