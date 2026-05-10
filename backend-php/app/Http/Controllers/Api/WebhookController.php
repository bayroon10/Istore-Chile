<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

            try {
                // URL temporal de n8n (luego la pondremos en el .env)
                $n8nWebhookUrl = env('N8N_WEBHOOK_URL', 'http://localhost:5678/webhook/istore-order-paid');
                
                // Cargar relaciones para tener todo el contexto de los items y su stock
                $order->load('items.product');

                Http::timeout(3)->post($n8nWebhookUrl, [
                    'event' => 'order_paid',
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                    'items' => $order->items->map(function($item) {
                        return [
                            'product_id' => $item->product_id,
                            'name' => $item->product_name,
                            'quantity_bought' => $item->quantity,
                            'current_stock' => $item->product ? $item->product->stock : 0 // Dato clave para la IA predictiva en n8n
                        ];
                    })
                ]);
                Log::info("Payload enviado a n8n para la orden #{$order->order_number}");
            } catch (\Exception $e) {
                // Si n8n está caído, no debemos romper el flujo de pago de la tienda
                Log::error("Fallo al enviar webhook a n8n: " . $e->getMessage());
            }
        } catch (\Exception $e) {
            Log::error("Error procesando pago para orden {$orderId}: " . $e->getMessage());
        }
    }
}
