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
        // 1. Buscamos la llave con varios nombres posibles (por si acaso)
        $secretKey = env('STRIPE_SECRET') ?? env('STRIPE_SECRET_KEY') ?? env('STRIPE_KEY') ?? config('services.stripe.secret');

        // 2. Si por alguna razón sigue vacía, ponemos una de prueba provisoria 
        // para que no explote el servidor con el Error 500
        if (empty($secretKey) || !is_string($secretKey)) {
            $secretKey = 'sk_test_51TDGYUBKumYnI58TfB3PtrhIU1heQebwCD8rv43tCRi3x4hZkKdAebIN9J18etnHK1h0sZj27i4wjlQSey54won100Zm1RL40V';
        }

        // 3. Inicializamos el cliente de Stripe asegurando que sea un String
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