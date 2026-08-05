<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private StripeService $stripeService,
    ) {
    }

    /**
     * Crea o recupera de forma idempotente una orden desde el carrito del usuario.
     * Stripe se invoca solo después de confirmar la transacción local.
     *
     * @throws Exception si el carrito está vacío, el stock es insuficiente o el pago no se puede preparar.
     */
    public function createOrderFromCart(
        User $user,
        array $shippingData,
        string $idempotencyKey,
        string $paymentMethod = 'stripe',
    ): array {
        $existingOrder = $this->findCheckoutOrder($user, $idempotencyKey);
        if ($existingOrder !== null) {
            return $this->checkoutResult($existingOrder, true);
        }

        try {
            $checkout = DB::transaction(function () use ($user, $shippingData, $idempotencyKey, $paymentMethod) {
                // Releer dentro de la sección protegida cubre solicitudes que llegaron en paralelo.
                $existingOrder = $this->findCheckoutOrder($user, $idempotencyKey, true);
                if ($existingOrder !== null) {
                    return ['order' => $existingOrder, 'replayed' => true];
                }

                $cart = Cart::query()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($cart === null) {
                    throw new Exception('El carrito está vacío. Agrega productos antes de hacer checkout.');
                }

                $cartItems = CartItem::query()
                    ->where('cart_id', $cart->id)
                    ->lockForUpdate()
                    ->get();

                if ($cartItems->isEmpty()) {
                    throw new Exception('El carrito está vacío. Agrega productos antes de hacer checkout.');
                }

                $productIds = $cartItems->pluck('product_id')->unique()->sort()->values()->all();
                $products = Product::query()
                    ->with('primaryImage')
                    ->whereIn('id', $productIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $subtotal = 0;
                $orderItemsData = [];

                foreach ($cartItems as $cartItem) {
                    $product = $products->get($cartItem->product_id);
                    if ($product === null) {
                        throw new Exception("El producto con ID {$cartItem->product_id} ya no existe.");
                    }

                    if (! $product->is_active) {
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
                    $orderItemsData[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_price' => $product->price,
                        'product_image' => $product->primaryImage?->image_url,
                        'quantity' => $cartItem->quantity,
                        'subtotal' => $itemSubtotal,
                    ];
                }

                $shippingCost = match ($shippingData['shipping_method']) {
                    'Starken' => 3990,
                    'Chilexpress' => 4500,
                    'Retiro' => 0,
                };
                $discount = 0;
                $total = $subtotal + $shippingCost - $discount;

                $order = $this->createPendingOrder([
                    'user_id' => $user->id,
                    'shipping_name' => $shippingData['shipping_name'],
                    'shipping_phone' => $shippingData['shipping_phone'],
                    'shipping_street' => $shippingData['shipping_street'],
                    'shipping_city' => $shippingData['shipping_city'],
                    'shipping_region' => $shippingData['shipping_region'],
                    'shipping_method' => $shippingData['shipping_method'],
                    'status' => 'pending',
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'discount' => $discount,
                    'total' => $total,
                    'payment_method' => $paymentMethod,
                    'checkout_idempotency_key' => $idempotencyKey,
                    'currency' => 'clp',
                    'notes' => $shippingData['notes'] ?? null,
                    'paid_at' => null,
                ]);

                foreach ($orderItemsData as $item) {
                    OrderItem::create([...$item, 'order_id' => $order->id]);
                }

                foreach ($cartItems as $cartItem) {
                    $product = $products->get($cartItem->product_id);
                    $product->decrement('stock', $cartItem->quantity);
                    $product->increment('sales_count', $cartItem->quantity);
                }

                CartItem::query()->where('cart_id', $cart->id)->delete();

                return ['order' => $order, 'replayed' => false];
            });
        } catch (QueryException $exception) {
            if (! $this->isCheckoutIdempotencyCollision($exception)) {
                throw $exception;
            }

            $winningOrder = $this->findCheckoutOrder($user, $idempotencyKey);
            if ($winningOrder === null) {
                throw $exception;
            }

            return $this->checkoutResult($winningOrder, true);
        }

        return $this->checkoutResult($checkout['order'], $checkout['replayed']);
    }

    protected function findCheckoutOrder(User $user, string $idempotencyKey, bool $lockForUpdate = false): ?Order
    {
        $query = Order::query()
            ->where('user_id', $user->id)
            ->where('checkout_idempotency_key', $idempotencyKey);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** @param array<string, mixed> $attributes */
    protected function createPendingOrder(array $attributes): Order
    {
        return Order::create($attributes);
    }

    private function checkoutResult(Order $order, bool $replayed): array
    {
        $clientSecret = null;
        if ($order->payment_method === 'stripe') {
            if ($order->stripe_payment_id !== null) {
                $paymentIntent = $this->stripeService->retrievePaymentIntent($order->stripe_payment_id);
                $clientSecret = $paymentIntent->client_secret ?? null;

                if (! is_string($clientSecret) || $clientSecret === '') {
                    throw new Exception('No se pudo recuperar la información de pago.');
                }
            } else {
                // Stripe deduplica esta provisión con checkout-order-{order_id}.
                $clientSecret = $this->stripeService->createPaymentIntent($order);
            }
        }

        return [
            'order' => $order->load('items'),
            'client_secret' => $clientSecret,
            'replayed' => $replayed,
        ];
    }

    private function isCheckoutIdempotencyCollision(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            && (
                str_contains($message, 'checkout_idempotency_key')
                || str_contains($message, 'orders_user_checkout_idempotency_unique')
            );
    }

    /**
     * Obtiene el historial de órdenes de un usuario.
     */
    public function getUserOrders(User $user, int $perPage = 10)
    {
        return Order::where('user_id', $user->id)
            ->notDraft()
            ->with(['items'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Obtiene una orden específica del usuario.
     */
    public function getUserOrder(User $user, int $orderId): Order
    {
        $order = Order::where('user_id', $user->id)
            ->notDraft()
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
        $query = Order::notDraft()
            ->with(['items', 'user'])
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

        match ($status) {
            'paid' => $order->paid_at = $order->paid_at ?? now(),
            'shipped' => $order->shipped_at = now(),
            'delivered' => $order->delivered_at = now(),
            default => null,
        };

        $order->save();
        $order->load(['items', 'user']);

        return $order;
    }
}