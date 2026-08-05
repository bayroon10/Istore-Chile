<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class WebhookController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
    ) {}

    /** Handles Stripe webhooks after signature verification. */
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $signature, $endpointSecret);
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response()->json(['error' => 'Invalid webhook'], 400);
        }

        try {
            $shouldRetry = $this->stripeService->processVerifiedWebhookEvent($event, $payload);
        } catch (\Throwable $exception) {
            Log::error('Stripe webhook handling failed.', [
                'exception_class' => $exception::class,
            ]);

            return response()->json(['status' => 'retry'], 500);
        }

        if ($shouldRetry) {
            return response()->json(['status' => 'retry'], 500);
        }

        return response()->json(['status' => 'success']);
    }
}
