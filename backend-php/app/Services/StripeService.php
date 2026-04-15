<?php

namespace App\Services;

use App\Models\Order;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;

class StripeService
{
    private StripeClient $stripe;

    public function __construct()
    {
        // Buscamos la llave probando con env('STRIPE_SECRET'), luego env('STRIPE_SECRET_KEY') y luego config('services.stripe.secret')
        $secretKey = env('STRIPE_SECRET') ?? env('STRIPE_SECRET_KEY') ?? config('services.stripe.secret');

        // Si todas fallan o están vacías, asignamos un string de prueba temporalmente
        if (empty($secretKey)) {
            $secretKey = 'sk_test_dummy_key';   
        }

        // Inicializamos el cliente de Stripe asegurando el cast a string
        $this->stripe = new StripeClient((string) $secretKey);
    }

    /**
     * Crea un PaymentIntent en Stripe para una orden específica.
     *
     * @param Order $order
     * @return string client_secret del PaymentIntent
     */
    public function createPaymentIntent(Order $order): string
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => (int) ($order->total), // Stripe usa montos enteros
                'currency' => 'clp',
                'description' => "Orden #{$order->order_number} - iStore Chile",
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'user_email' => $order->user->email,
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            // Guardamos el ID del PaymentIntent en la orden para tracking
            $order->update(['stripe_payment_id' => $paymentIntent->id]);

            return $paymentIntent->client_secret;
        } catch (\Exception $e) {
            Log::error("Error creando PaymentIntent para orden {$order->id}: " . $e->getMessage());
            throw new \Exception("Error con el procesador de pagos (Stripe): " . $e->getMessage());
        }
    }
}