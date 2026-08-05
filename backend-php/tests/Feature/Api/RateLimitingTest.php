<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that rate limiting triggers 429 after exceeding limit on throttled routes.
     */
    public function test_rate_limiting_triggers_429_on_throttled_routes(): void
    {
        Product::factory()->create(['is_active' => true]);

        // Send 60 requests which should succeed
        for ($i = 0; $i < 60; $i++) {
            $response = $this->getJson('/api/products');
            $response->assertStatus(200);
        }

        // The 61st request within the same minute window should return 429 Too Many Requests
        $responseExceeded = $this->getJson('/api/products');
        $responseExceeded->assertStatus(429);
    }
}
