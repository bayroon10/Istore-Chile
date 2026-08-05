<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StripeWebhookEvent extends Model
{
    public const PROCESSING = 'processing';

    public const PROCESSED = 'processed';

    public const REJECTED = 'rejected';

    public const RETRYABLE_FAILED = 'retryable_failed';

    protected $fillable = [
        'stripe_event_id',
        'event_type',
        'status',
        'order_id',
        'payment_intent_id',
        'payload_fingerprint',
        'failure_code',
        'rejection_code',
        'attempt_count',
        'processing_started_at',
        'lease_expires_at',
        'processed_at',
    ];

    protected $casts = [
        'processing_started_at' => 'datetime',
        'lease_expires_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
