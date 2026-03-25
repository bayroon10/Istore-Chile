<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;

class WebhookController extends Controller
{
    public function procesarWebhook(Request $request)
    {
        // 1. Agarramos la llave secreta del Webhook (la crearemos en un rato)
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $event = null;

        // 2. Verificamos que el mensaje venga 100% de Stripe y no de un hacker
        try {
            $event = Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        } catch(\UnexpectedValueException $e) {
            return response()->json(['error' => 'Payload inválido'], 400);
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Firma inválida'], 400);
        }

        // 3. Revisamos qué evento nos mandó Stripe
        if ($event->type == 'payment_intent.succeeded') {
            $pago = $event->data->object;
            
            // 🌟 AQUÍ EN EL FUTURO MOVEREMOS LA LÓGICA DE GUARDAR EL PEDIDO
            // Por ahora, solo dejaremos un registro en el sistema de que funcionó
            Log::info('¡WEBHOOK RECIBIDO! Pago exitoso por: $' . ($pago->amount));
        }

        // Siempre hay que responderle "200 OK" a Stripe para que sepa que lo escuchamos
        return response()->json(['status' => 'success'], 200);
    }
}