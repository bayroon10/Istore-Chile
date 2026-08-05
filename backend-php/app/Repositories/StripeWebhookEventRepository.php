<?php

namespace App\Repositories;

use App\Models\StripeWebhookEvent;
use Illuminate\Support\Facades\DB;

class StripeWebhookEventRepository
{
    private const LEASE_SECONDS = 300;

    /**
     * Atomically claims an event for processing, or returns null when another
     * request has already completed or currently owns it.
     */
    public function claim(
        string $stripeEventId,
        string $eventType,
        ?string $paymentIntentId,
        string $payloadFingerprint,
    ): ?StripeWebhookEvent {
        return DB::transaction(function () use ($stripeEventId, $eventType, $paymentIntentId, $payloadFingerprint) {
            $now = now();
            $inserted = DB::table('stripe_webhook_events')->insertOrIgnore([
                'stripe_event_id' => $stripeEventId,
                'event_type' => $eventType,
                'status' => StripeWebhookEvent::PROCESSING,
                'payment_intent_id' => $paymentIntentId,
                'payload_fingerprint' => $payloadFingerprint,
                'attempt_count' => 1,
                'processing_started_at' => $now,
                'lease_expires_at' => $now->copy()->addSeconds(self::LEASE_SECONDS),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $event = StripeWebhookEvent::query()
                ->where('stripe_event_id', $stripeEventId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inserted === 1) {
                return $event;
            }

            $leaseExpired = $event->lease_expires_at === null || $event->lease_expires_at->isPast();
            $canRetry = $event->status === StripeWebhookEvent::RETRYABLE_FAILED
                || ($event->status === StripeWebhookEvent::PROCESSING && $leaseExpired);

            if (! $canRetry) {
                return null;
            }

            $event->forceFill([
                'status' => StripeWebhookEvent::PROCESSING,
                'failure_code' => null,
                'rejection_code' => null,
                'attempt_count' => $event->attempt_count + 1,
                'processing_started_at' => $now,
                'lease_expires_at' => $now->copy()->addSeconds(self::LEASE_SECONDS),
            ])->save();

            return $event;
        });
    }

    public function markProcessed(int $eventId, ?int $orderId = null): void
    {
        $this->updateProcessingEvent($eventId, [
            'status' => StripeWebhookEvent::PROCESSED,
            'order_id' => $orderId,
            'lease_expires_at' => null,
            'processed_at' => now(),
        ]);
    }

    public function markRejected(int $eventId, string $rejectionCode): void
    {
        $this->updateProcessingEvent($eventId, [
            'status' => StripeWebhookEvent::REJECTED,
            'lease_expires_at' => null,
            'rejection_code' => $rejectionCode,
        ]);
    }

    public function markRetryableFailed(int $eventId, string $failureCode): void
    {
        $this->updateProcessingEvent($eventId, [
            'status' => StripeWebhookEvent::RETRYABLE_FAILED,
            'lease_expires_at' => null,
            'failure_code' => $failureCode,
        ]);
    }

    private function updateProcessingEvent(int $eventId, array $attributes): void
    {
        DB::transaction(function () use ($eventId, $attributes) {
            $event = StripeWebhookEvent::query()->lockForUpdate()->findOrFail($eventId);

            if ($event->status !== StripeWebhookEvent::PROCESSING) {
                return;
            }

            $event->forceFill($attributes)->save();
        });
    }
}
