<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Exception;

class PedidoController extends Controller
{
    public function index()
    {
        $pedidos = Pedido::orderBy('created_at', 'desc')->get();
        return response()->json($pedidos);
    }

    public function store(Request $request)
    {
        // ========================================================
        // 💳 FASE 1: PROCESAR EL PAGO CON STRIPE
        // ========================================================
        try {
            Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

            $pago = PaymentIntent::create([
                'amount' => (int) $request->total,
                'currency' => 'clp', 
                'payment_method' => $request->stripe_token, 
                'confirm' => true, 
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never'
                ]
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        // ========================================================
        // 📦 FASE 2: GUARDAR PEDIDO Y STOCK
        // ========================================================
        $pedido = Pedido::create($request->all());

        if (isset($request->carrito) && is_array($request->carrito)) {
            foreach ($request->carrito as $item) {
                $producto = Producto::find($item['id']);
                if ($producto) {
                    $producto->stock_actual -= $item['cantidad'];
                    $producto->save();
                }
            }
        }

        // ========================================================
        // 📧 FASE 3: ENVIAR BOLETA
        // ========================================================
        try {
            $pdf = Pdf::loadView('pdf.boleta', ['pedido' => $pedido]);
            Mail::send([], [], function($mensaje) use ($pedido, $pdf) {
                $mensaje->to($pedido->cliente_email)
                        ->subject('✅ Confirmación de Compra - iStore Chile')
                        ->html('<h2>¡Hola ' . $pedido->cliente_nombre . '!</h2>
                                <p>Hemos recibido tu pago exitosamente. Adjuntamos la boleta oficial de tu compra.</p>
                                <p>Enviaremos tu paquete por <b>' . $pedido->metodo_envio . '</b>.</p>')
                        ->attachData($pdf->output(), 'Boleta_iStore_'.$pedido->id.'.pdf', [
                            'mime' => 'application/pdf',
                        ]);
            });
        } catch (Exception $e) {
            Log::error('Error correo: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Pago exitoso', 'pedido' => $pedido], 201);
    }

    public function update(Request $request, $id)
    {
        $pedido = Pedido::find($id);
        if ($pedido) {
            $pedido->estado = $request->estado;
            $pedido->save();
            return response()->json(['message' => 'Estado actualizado']);
        }
        return response()->json(['error' => 'Pedido no encontrado'], 404);
    }
}