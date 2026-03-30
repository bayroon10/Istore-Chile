<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
            Stripe::setApiKey(config('services.stripe.secret'));

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
        // 📦 FASE 2: GUARDAR PEDIDO Y DESCONTAR STOCK (ATÓMICO)
        // ========================================================
        // Usamos una transacción para garantizar que:
        //   - Si el stock es insuficiente, NO se crea el pedido
        //   - Si dos clientes compran al mismo tiempo, uno espera al otro
        //   - Si algo falla, TODO se revierte (rollback automático)
        try {
            $pedido = DB::transaction(function () use ($request) {
                $pedido = Pedido::create($request->all());

                if (isset($request->carrito) && is_array($request->carrito)) {
                    foreach ($request->carrito as $item) {
                        // lockForUpdate() bloquea la fila en PostgreSQL hasta que termine la transacción
                        // Esto impide que otro request lea el stock mientras lo estamos modificando
                        $producto = Producto::lockForUpdate()->find($item['id']);

                        if (!$producto) {
                            throw new Exception("Producto con ID {$item['id']} no encontrado.");
                        }

                        if ($producto->stock_actual < $item['cantidad']) {
                            throw new Exception("Stock insuficiente para '{$producto->nombre}'. Disponible: {$producto->stock_actual}, solicitado: {$item['cantidad']}.");
                        }

                        // decrement() ejecuta UPDATE ... SET stock_actual = stock_actual - N
                        // Es una operación atómica a nivel de SQL
                        $producto->decrement('stock_actual', $item['cantidad']);
                    }
                }

                return $pedido;
            });
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
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