<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class WebhookController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * Maneja los webhooks enviados por Stripe.
     */
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $payload, $sigHeader, $endpointSecret
            );
        } catch (UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Manejar el evento
        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $this->handleCheckoutSessionCompleted($session);
                break;

            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                $this->handlePaymentIntentSucceeded($paymentIntent);
                break;
            
            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                Log::warning("Pago fallido para PI: {$paymentIntent->id}");
                break;

            default:
                Log::info("Evento de Stripe no manejado: {$event->type}");
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Maneja la sesión de checkout completada.
     */
    private function handleCheckoutSessionCompleted($session)
    {
        $orderId = $session->metadata->order_id ?? null;

        if (!$orderId) {
            Log::error("Webhook error: No order_id in metadata for Session {$session->id}");
            return;
        }

        $this->processOrderPayment($orderId);
    }

    /**
     * Procesa una orden cuando el pago ha sido exitoso.
     */
    private function handlePaymentIntentSucceeded($paymentIntent)
    {
        $orderId = $paymentIntent->metadata->order_id ?? null;

        if (!$orderId) {
            Log::error("Webhook error: No order_id in metadata for PI {$paymentIntent->id}");
            return;
        }

        $this->processOrderPayment($orderId);
    }

    /**
     * Lógica común para marcar una orden como pagada.
     */
    private function processOrderPayment($orderId)
    {
        try {
            $order = Order::findOrFail($orderId);

            if ($order->status === 'paid') {
                return;
            }

            $this->orderService->updateOrderStatus($order->id, 'paid');
            Log::info("Orden #{$order->order_number} marcada como PAGADA vía Webhook.");
        } catch (\Exception $e) {
            Log::error("Error procesando pago para orden {$orderId}: " . $e->getMessage());
        }
    }
}
