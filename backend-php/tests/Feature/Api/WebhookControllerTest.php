<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\User;
use App\Repositories\StripeWebhookEventRepository;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_local_feature_test_secret';

    private array $paymentIntents = [];

    private int $paymentIntentRetrievals = 0;

    private string $lastPayload = '';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => $this->webhookSecret]);
        Http::fake();

        $stripeService = new StripeService(new StripeWebhookEventRepository);
        $stripeService->setPaymentIntentRetriever(function (string $paymentIntentId): object {
            $this->paymentIntentRetrievals++;

            return $this->paymentIntents[$paymentIntentId]
                ?? throw new RuntimeException('PaymentIntent test double not found.');
        });

        $this->app->instance(StripeService::class, $stripeService);
    }

    public function test_invalid_signature_creates_no_webhook_ledger_record(): void
    {
        $payload = json_encode([
            'id' => 'evt_invalid_signature',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_invalid_signature']],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=invalid',
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(400);
        $this->assertDatabaseCount('stripe_webhook_events', 0);
        $this->assertSame(0, $this->paymentIntentRetrievals);
    }

    public function test_matching_payment_intent_marks_order_paid_once_and_processes_ledger(): void
    {
        $order = $this->createPendingStripeOrder('pi_matching');
        $this->stubPaymentIntent($order, 'pi_matching');

        $response = $this->postPaymentIntentSucceeded('evt_matching', 'pi_matching');

        $response->assertOk()->assertJson(['status' => 'success']);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_matching',
            'event_type' => 'payment_intent.succeeded',
            'payment_intent_id' => 'pi_matching',
            'order_id' => $order->id,
            'status' => 'processed',
            'attempt_count' => 1,
        ]);
        $this->assertSame(1, $this->paymentIntentRetrievals);
    }

    public function test_ledger_payload_fingerprint_is_hmac_sha256_of_the_verified_payload(): void
    {
        $order = $this->createPendingStripeOrder('pi_hmac_fingerprint');
        $this->stubPaymentIntent($order, 'pi_hmac_fingerprint');

        $this->postPaymentIntentSucceeded('evt_hmac_fingerprint', 'pi_hmac_fingerprint')->assertOk();

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_hmac_fingerprint',
            'payload_fingerprint' => hash_hmac('sha256', $this->lastPayload, config('app.key')),
        ]);
    }

    public function test_valid_duplicate_returns_success_without_reprocessing_or_notifying(): void
    {
        $order = $this->createPendingStripeOrder('pi_duplicate');
        $this->stubPaymentIntent($order, 'pi_duplicate');

        $this->postPaymentIntentSucceeded('evt_duplicate', 'pi_duplicate')->assertOk();
        $response = $this->postPaymentIntentSucceeded('evt_duplicate', 'pi_duplicate');

        $response->assertOk()->assertJson(['status' => 'success']);
        $this->assertDatabaseCount('stripe_webhook_events', 1);
        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_duplicate',
            'status' => 'processed',
            'attempt_count' => 1,
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertSame(1, $this->paymentIntentRetrievals);
        Http::assertNothingSent();
    }

    public function test_amount_mismatch_rejects_event_without_changing_order(): void
    {
        $order = $this->createPendingStripeOrder('pi_amount_mismatch');
        $this->stubPaymentIntent($order, 'pi_amount_mismatch', amount: 9999, amountReceived: 9999);

        $response = $this->postPaymentIntentSucceeded('evt_amount_mismatch', 'pi_amount_mismatch');

        $response->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_amount_mismatch',
            'status' => 'rejected',
            'rejection_code' => 'amount_mismatch',
        ]);
    }

    public function test_currency_mismatch_rejects_event_without_changing_order(): void
    {
        $order = $this->createPendingStripeOrder('pi_currency_mismatch');
        $this->stubPaymentIntent($order, 'pi_currency_mismatch', currency: 'usd');

        $response = $this->postPaymentIntentSucceeded('evt_currency_mismatch', 'pi_currency_mismatch');

        $response->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_currency_mismatch',
            'status' => 'rejected',
            'rejection_code' => 'currency_mismatch',
        ]);
    }

    public function test_checkout_completion_is_recorded_without_marking_order_paid(): void
    {
        $order = $this->createPendingStripeOrder('pi_checkout_ignored');

        $response = $this->postStripeEvent('evt_checkout_ignored', 'checkout.session.completed', [
            'id' => 'cs_checkout_ignored',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_checkout_ignored',
            'status' => 'processed',
        ]);
        $this->assertSame(0, $this->paymentIntentRetrievals);
    }

    public function test_payment_intent_retrieval_failure_is_retryable_and_returns_5xx(): void
    {
        $order = $this->createPendingStripeOrder('pi_retryable_failure');
        $stripeService = app(StripeService::class);
        $stripeService->setPaymentIntentRetriever(fn (): object => throw new RuntimeException('dependency unavailable'));

        $response = $this->postPaymentIntentSucceeded('evt_retryable_failure', 'pi_retryable_failure');

        $response->assertStatus(500)->assertJson(['status' => 'retry']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_retryable_failure',
            'status' => 'retryable_failed',
            'failure_code' => 'payment_intent_retrieval_failed',
            'attempt_count' => 1,
        ]);
    }

    private function createPendingStripeOrder(string $paymentIntentId): Order
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'shipping_name' => 'Test customer',
            'shipping_phone' => '000000000',
            'shipping_street' => 'Test street',
            'shipping_city' => 'Test city',
            'shipping_region' => 'Test region',
            'shipping_method' => 'Retiro',
            'status' => 'pending',
            'subtotal' => 10000,
            'shipping_cost' => 0,
            'discount' => 0,
            'total' => 10000,
            'payment_method' => 'stripe',
        ]);
        $order->forceFill([
            'stripe_payment_id' => $paymentIntentId,
            'currency' => 'clp',
        ])->save();

        return $order;
    }

    private function stubPaymentIntent(
        Order $order,
        string $paymentIntentId,
        int $amount = 10000,
        int $amountReceived = 10000,
        string $currency = 'CLP',
    ): void {
        $this->paymentIntents[$paymentIntentId] = (object) [
            'id' => $paymentIntentId,
            'status' => 'succeeded',
            'amount' => $amount,
            'amount_received' => $amountReceived,
            'currency' => $currency,
            'metadata' => (object) ['order_id' => (string) $order->id],
        ];
    }

    private function postPaymentIntentSucceeded(string $eventId, string $paymentIntentId): TestResponse
    {
        return $this->postStripeEvent($eventId, 'payment_intent.succeeded', [
            'id' => $paymentIntentId,
        ]);
    }

    private function postStripeEvent(string $eventId, string $eventType, array $object): TestResponse
    {
        $payload = json_encode([
            'id' => $eventId,
            'type' => $eventType,
            'data' => ['object' => $object],
        ], JSON_THROW_ON_ERROR);
        $this->lastPayload = $payload;
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $this->webhookSecret);

        return $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }
}
