<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test payment_intent.succeeded webhook updates order status to paid.
     */
    public function test_stripe_webhook_marks_order_as_paid(): void
    {
        // 1. Arrange: Create user and a pending order
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'shipping_name' => 'Bairon Castro',
            'shipping_phone' => '+56998765432',
            'shipping_street' => 'Avenida Apoquindo 3000',
            'shipping_city' => 'Las Condes',
            'shipping_region' => 'Metropolitana',
            'shipping_method' => 'Retiro',
            'status' => 'pending',
            'subtotal' => 10000,
            'shipping_cost' => 0,
            'discount' => 0,
            'total' => 10000,
            'payment_method' => 'stripe',
        ]);

        // 2. Arrange: Mock the static Webhook::constructEvent call
        // We create a nested structure to mock $event->type and $event->data->object
        $mockEvent = new \stdClass();
        $mockEvent->type = 'payment_intent.succeeded';
        
        $mockObject = new \stdClass();
        $mockObject->id = 'pi_test_webhook_123';
        $mockObject->metadata = new \stdClass();
        $mockObject->metadata->order_id = $order->id;
        
        $mockData = new \stdClass();
        $mockData->object = $mockObject;
        
        $mockEvent->data = $mockData;

        // Alias mock Stripe\Webhook
        $webhookClassMock = Mockery::mock('alias:Stripe\Webhook');
        $webhookClassMock->shouldReceive('constructEvent')
            ->once()
            ->andReturn($mockEvent);

        // 3. Act: Post the webhook event payload to /api/webhooks/stripe
        $response = $this->postJson('/api/webhooks/stripe', [
            'id' => 'evt_test_123',
            'type' => 'payment_intent.succeeded',
        ], [
            'Stripe-Signature' => 't=123,v1=abc',
        ]);

        // 4. Assert: Correct HTTP status response and order marked as paid in the database
        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
    }
}
