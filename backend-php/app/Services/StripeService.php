<?php

namespace App\Services;

use App\Models\Order;
use App\Models\StripeWebhookEvent;
use App\Repositories\StripeWebhookEventRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Throwable;

class StripeService
{
    private ?StripeClient $stripe = null;

    /** @var (\Closure(string): object)|null */
    private ?\Closure $paymentIntentRetriever = null;

    public function __construct(
        private ?StripeWebhookEventRepository $webhookEvents = null,
    ) {
        $this->webhookEvents ??= new StripeWebhookEventRepository;
    }

    /**
     * Replaces remote retrieval for deterministic tests or an approved adapter.
     * No test needs to contact Stripe.
     *
     * @param  \Closure(string): object  $retriever
     */
    public function setPaymentIntentRetriever(\Closure $retriever): void
    {
        $this->paymentIntentRetriever = $retriever;
    }

    /** @return object Stripe PaymentIntent-compatible object */
    public function retrievePaymentIntent(string $paymentIntentId): object
    {
        if ($this->paymentIntentRetriever !== null) {
            return ($this->paymentIntentRetriever)($paymentIntentId);
        }

        return $this->stripeClient()->paymentIntents->retrieve($paymentIntentId, []);
    }

    /**
     * Crea un PaymentIntent en Stripe para una orden específica.
     */
    public function createPaymentIntent(Order $order): string
    {
        try {
            $paymentIntent = $this->stripeClient()->paymentIntents->create([
                'amount' => (int) $order->total,
                'currency' => 'clp',
                'description' => "Orden #{$order->order_number} - iStore Chile",
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ],
                'automatic_payment_methods' => ['enabled' => true],
            ], [
                'idempotency_key' => "checkout-order-{$order->id}",
            ]);

            $order->update(['stripe_payment_id' => $paymentIntent->id]);

            return $paymentIntent->client_secret;
        } catch (Throwable $exception) {
            Log::error('PaymentIntent creation failed.', [
                'order_id' => $order->id,
                'exception_class' => $exception::class,
            ]);

            throw new \Exception('No se pudo procesar el pago.', 0, $exception);
        }
    }

    /**
     * Processes a Stripe event only after the controller has verified its signature.
     * Returns true when Stripe must retry because a transient dependency failed.
     */
    public function processVerifiedWebhookEvent(object $event, string $payload): bool
    {
        $eventId = (string) ($event->id ?? '');
        $eventType = (string) ($event->type ?? '');

        if ($eventId === '' || $eventType === '') {
            Log::warning('Stripe webhook missing required event identifiers.', [
                'event_id_present' => $eventId !== '',
                'event_type_present' => $eventType !== '',
            ]);

            return false;
        }

        $eventPaymentIntentId = $eventType === 'payment_intent.succeeded'
            ? $this->stringProperty($event->data->object ?? null, 'id')
            : null;

        try {
            $ledgerEvent = $this->webhookEvents->claim(
                stripeEventId: $eventId,
                eventType: $eventType,
                paymentIntentId: $eventPaymentIntentId,
                payloadFingerprint: $this->payloadFingerprint($payload),
            );

            if ($ledgerEvent === null) {
                return false;
            }

            if ($eventType !== 'payment_intent.succeeded') {
                $this->webhookEvents->markProcessed($ledgerEvent->id);
                Log::info('Stripe webhook event ignored.', [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                ]);

                return false;
            }

            if ($eventPaymentIntentId === null) {
                $this->webhookEvents->markRejected($ledgerEvent->id, 'missing_payment_intent_id');
                $this->logRejected($eventId, $eventType, 'missing_payment_intent_id');

                return false;
            }

            try {
                $paymentIntent = $this->retrievePaymentIntent($eventPaymentIntentId);
            } catch (Throwable $exception) {
                $this->webhookEvents->markRetryableFailed($ledgerEvent->id, 'payment_intent_retrieval_failed');
                Log::error('Stripe PaymentIntent retrieval failed.', [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'exception_class' => $exception::class,
                ]);

                return true;
            }

            $outcome = $this->completePaymentIntentEvent(
                ledgerEventId: $ledgerEvent->id,
                eventId: $eventId,
                eventPaymentIntentId: $eventPaymentIntentId,
                paymentIntent: $paymentIntent,
            );

            if ($outcome !== null) {
                $this->logRejected($eventId, $eventType, $outcome);
            }

            return false;
        } catch (Throwable $exception) {
            try {
                if (isset($ledgerEvent)) {
                    $this->webhookEvents->markRetryableFailed($ledgerEvent->id, 'webhook_processing_failed');
                }
            } catch (Throwable) {
                // The original exception remains the only observable failure.
            }

            Log::error('Stripe webhook processing failed.', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'exception_class' => $exception::class,
            ]);

            return true;
        }
    }

    /**
     * Returns a rejection code when validation fails, otherwise null.
     */
    private function completePaymentIntentEvent(
        int $ledgerEventId,
        string $eventId,
        string $eventPaymentIntentId,
        object $paymentIntent,
    ): ?string {
        return DB::transaction(function () use ($ledgerEventId, $eventId, $eventPaymentIntentId, $paymentIntent) {
            $ledgerEvent = StripeWebhookEvent::query()->lockForUpdate()->findOrFail($ledgerEventId);

            if ($ledgerEvent->status !== StripeWebhookEvent::PROCESSING) {
                return null;
            }

            $metadataOrderId = $this->stringProperty($this->property($paymentIntent, 'metadata'), 'order_id');
            if ($metadataOrderId === null) {
                $this->rejectWithinTransaction($ledgerEvent, 'missing_order_metadata');

                return 'missing_order_metadata';
            }

            $order = Order::query()->lockForUpdate()->find($metadataOrderId);
            if ($order === null) {
                $this->rejectWithinTransaction($ledgerEvent, 'order_not_found');

                return 'order_not_found';
            }

            $rejectionCode = $this->paymentIntentRejectionCode(
                order: $order,
                eventPaymentIntentId: $eventPaymentIntentId,
                paymentIntent: $paymentIntent,
            );

            if ($rejectionCode !== null) {
                $this->rejectWithinTransaction($ledgerEvent, $rejectionCode, $order->id);

                return $rejectionCode;
            }

            $order->forceFill([
                'status' => 'paid',
                'paid_at' => $order->paid_at ?? now(),
            ])->save();

            $ledgerEvent->forceFill([
                'status' => StripeWebhookEvent::PROCESSED,
                'order_id' => $order->id,
                'lease_expires_at' => null,
                'processed_at' => now(),
            ])->save();

            Log::info('Stripe payment event completed.', [
                'event_id' => $eventId,
                'order_id' => $order->id,
            ]);

            return null;
        });
    }

    private function paymentIntentRejectionCode(
        Order $order,
        string $eventPaymentIntentId,
        object $paymentIntent,
    ): ?string {
        if ($eventPaymentIntentId !== (string) $order->stripe_payment_id
            || $this->stringProperty($paymentIntent, 'id') !== $eventPaymentIntentId) {
            return 'payment_intent_mismatch';
        }

        if ($this->stringProperty($paymentIntent, 'status') !== 'succeeded') {
            return 'payment_intent_not_succeeded';
        }

        $expectedAmount = $this->normaliseWholeAmount($order->total);
        if ($expectedAmount === null
            || $this->normaliseWholeAmount($this->property($paymentIntent, 'amount')) !== $expectedAmount) {
            return 'amount_mismatch';
        }

        if ($this->normaliseWholeAmount($this->property($paymentIntent, 'amount_received')) !== $expectedAmount) {
            return 'amount_received_mismatch';
        }

        $paymentIntentCurrency = $this->stringProperty($paymentIntent, 'currency');
        if ($paymentIntentCurrency === null
            || strtolower($paymentIntentCurrency) !== strtolower((string) $order->currency)) {
            return 'currency_mismatch';
        }

        $metadataOrderId = $this->stringProperty($this->property($paymentIntent, 'metadata'), 'order_id');
        if ($metadataOrderId !== (string) $order->id) {
            return 'order_metadata_mismatch';
        }

        if ($order->payment_method !== 'stripe' || $order->status !== 'pending') {
            return 'order_not_admissible';
        }

        return null;
    }

    private function rejectWithinTransaction(
        StripeWebhookEvent $ledgerEvent,
        string $rejectionCode,
        ?int $orderId = null,
    ): void {
        $ledgerEvent->forceFill([
            'status' => StripeWebhookEvent::REJECTED,
            'order_id' => $orderId,
            'lease_expires_at' => null,
            'rejection_code' => $rejectionCode,
        ])->save();
    }

    private function logRejected(string $eventId, string $eventType, string $rejectionCode): void
    {
        Log::warning('Stripe webhook event rejected.', [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'rejection_code' => $rejectionCode,
        ]);
    }

    private function payloadFingerprint(string $payload): string
    {
        $applicationKey = config('app.key');

        if (! is_string($applicationKey) || $applicationKey === '') {
            throw new \LogicException('Application key is unavailable for webhook fingerprinting.');
        }

        return hash_hmac('sha256', $payload, $applicationKey);
    }

    private function normaliseWholeAmount(mixed $amount): ?string
    {
        if (is_int($amount)) {
            return $amount >= 0 ? (string) $amount : null;
        }

        if (! is_string($amount) || ! preg_match('/^\d+(?:\.0+)?$/', $amount)) {
            return null;
        }

        return ltrim(strtok($amount, '.'), '0') ?: '0';
    }

    private function stringProperty(mixed $value, string $property): ?string
    {
        $propertyValue = $this->property($value, $property);

        return is_scalar($propertyValue) ? (string) $propertyValue : null;
    }

    private function property(mixed $value, string $property): mixed
    {
        if (is_array($value)) {
            return $value[$property] ?? null;
        }

        if (is_object($value)) {
            return $value->{$property} ?? null;
        }

        return null;
    }

    private function stripeClient(): StripeClient
    {
        if ($this->stripe !== null) {
            return $this->stripe;
        }

        $secretKey = config('services.stripe.secret');
        $this->stripe = new StripeClient(
            is_string($secretKey) && $secretKey !== '' ? $secretKey : 'sk_test_dummy_key',
        );

        return $this->stripe;
    }
}
