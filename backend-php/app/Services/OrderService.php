<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderService
{
    /**
     * Crea una orden a partir del carrito del usuario.
     *
     * Flujo transaccional:
     * 1. Bloquear los productos con lockForUpdate (nivel fila en PostgreSQL)
     * 2. Validar stock de cada ítem
     * 3. Crear la orden con los datos de envío
     * 4. Crear los order_items con snapshot del precio/nombre/imagen
     * 5. Descontar stock con decrement() (atómico a nivel SQL)
     * 6. Vaciar el carrito
     * 7. Actualizar sales_count de cada producto
     *
     * Si CUALQUIER paso falla → rollback automático → no se cobra, no se descuenta nada.
     *
     * @param User   $user         Usuario autenticado
     * @param array  $shippingData Datos de dirección de envío
     * @param string $paymentMethod Método de pago ('stripe', etc.)
     * @param string|null $stripePaymentId ID del pago de Stripe (si aplica)
     *
     * @throws Exception si el carrito está vacío, stock insuficiente, etc.
     */
    public function createOrderFromCart(
        User $user,
        array $shippingData,
        string $paymentMethod = 'stripe',
        ?string $stripePaymentId = null,
    ): Order {
        // Obtener el carrito del usuario con sus ítems
        $cart = Cart::where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            throw new Exception('El carrito está vacío. Agrega productos antes de hacer checkout.');
        }

        return DB::transaction(function () use ($cart, $user, $shippingData, $paymentMethod, $stripePaymentId) {

            $subtotal = 0;
            $orderItems = [];

            // -------------------------------------------------------
            // Paso 1: Validar stock y preparar ítems
            // -------------------------------------------------------
            foreach ($cart->items as $cartItem) {
                // lockForUpdate() bloquea la fila del producto hasta que termine la transacción
                // Si otro request intenta comprar el mismo producto, ESPERA aquí
                $product = Product::lockForUpdate()->find($cartItem->product_id);

                if (!$product) {
                    throw new Exception("El producto '{$cartItem->product->name}' ya no existe.");
                }

                if (!$product->is_active) {
                    throw new Exception("El producto '{$product->name}' ya no está disponible.");
                }

                if ($product->stock < $cartItem->quantity) {
                    throw new Exception(
                        "Stock insuficiente para '{$product->name}'. " .
                        "Disponible: {$product->stock}, solicitado: {$cartItem->quantity}."
                    );
                }

                $itemSubtotal = $product->price * $cartItem->quantity;
                $subtotal += $itemSubtotal;

                // Preparar el snapshot del order item
                $orderItems[] = [
                    'product_id'    => $product->id,
                    'product_name'  => $product->name,
                    'product_price' => $product->price,
                    'product_image' => $product->primaryImage?->url,
                    'quantity'      => $cartItem->quantity,
                    'subtotal'      => $itemSubtotal,
                ];
            }

            // -------------------------------------------------------
            // Paso 2: Calcular totales
            // -------------------------------------------------------
            $shippingCost = $shippingData['shipping_cost'] ?? 0;
            $discount     = $shippingData['discount'] ?? 0;
            $total        = $subtotal + $shippingCost - $discount;

            // -------------------------------------------------------
            // Paso 3: Crear la orden
            // -------------------------------------------------------
            $order = Order::create([
                'user_id'          => $user->id,
                'shipping_name'    => $shippingData['shipping_name'],
                'shipping_phone'   => $shippingData['shipping_phone'],
                'shipping_street'  => $shippingData['shipping_street'],
                'shipping_city'    => $shippingData['shipping_city'],
                'shipping_region'  => $shippingData['shipping_region'],
                'shipping_method'  => $shippingData['shipping_method'],
                'status'           => $stripePaymentId ? 'paid' : 'pending',
                'subtotal'         => $subtotal,
                'shipping_cost'    => $shippingCost,
                'discount'         => $discount,
                'total'            => $total,
                'payment_method'   => $paymentMethod,
                'stripe_payment_id' => $stripePaymentId,
                'notes'            => $shippingData['notes'] ?? null,
                'paid_at'          => $stripePaymentId ? now() : null,
            ]);

            // -------------------------------------------------------
            // Paso 4: Crear order items + descontar stock
            // -------------------------------------------------------
            foreach ($orderItems as $item) {
                OrderItem::create(array_merge($item, [
                    'order_id' => $order->id,
                ]));

                // decrement() ejecuta UPDATE SET stock = stock - N (atómico en SQL)
                $product = Product::find($item['product_id']);
                $product->decrement('stock', $item['quantity']);

                // Incrementar contador de ventas
                $product->increment('sales_count', $item['quantity']);
            }

            // -------------------------------------------------------
            // Paso 5: Vaciar el carrito
            // -------------------------------------------------------
            $cart->items()->delete();

            // Cargar relaciones antes de devolver
            $order->load(['items', 'user']);

            return $order;
        });
    }

    /**
     * Obtiene el historial de órdenes de un usuario.
     */
    public function getUserOrders(User $user, int $perPage = 10)
    {
        return Order::where('user_id', $user->id)
            ->with(['items'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Obtiene una orden específica del usuario (validando propiedad).
     */
    public function getUserOrder(User $user, int $orderId): Order
    {
        $order = Order::where('user_id', $user->id)
            ->with(['items'])
            ->find($orderId);

        if (!$order) {
            throw new Exception('Orden no encontrada.');
        }

        return $order;
    }

    /**
     * Obtiene todas las órdenes (para admin).
     */
    public function getAllOrders(int $perPage = 15, ?string $status = null)
    {
        $query = Order::with(['items', 'user'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * Actualiza el estado de una orden (para admin).
     */
    public function updateOrderStatus(int $orderId, string $status): Order
    {
        $order = Order::findOrFail($orderId);

        $validStatuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'];

        if (!in_array($status, $validStatuses)) {
            throw new Exception("Estado '{$status}' no es válido.");
        }

        $order->status = $status;

        // Timestamps de ciclo de vida automáticos
        match($status) {
            'paid'      => $order->paid_at      = $order->paid_at ?? now(),
            'shipped'   => $order->shipped_at   = now(),
            'delivered' => $order->delivered_at  = now(),
            default     => null,
        };

        $order->save();
        $order->load(['items', 'user']);

        return $order;
    }
}
