<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class CartService
{
    /**
     * Obtiene o crea el carrito del usuario/invitado.
     *
     * Lógica:
     * - Si hay un User autenticado → busca por user_id
     * - Si es invitado → busca por session_id
     * - Si no existe → crea uno nuevo
     */
    public function getCart(?User $user, ?string $sessionId = null): Cart
    {
        if ($user) {
            return Cart::firstOrCreate(
                ['user_id' => $user->id],
            );
        }

        if (!$sessionId) {
            throw new Exception('Se requiere un session_id para carritos de invitado.');
        }

        return Cart::firstOrCreate(
            ['session_id' => $sessionId],
        );
    }

    /**
     * Devuelve el carrito con sus ítems y productos cargados.
     */
    public function getCartWithDetails(?User $user, ?string $sessionId = null): Cart
    {
        $cart = $this->getCart($user, $sessionId);

        // Eager load de items + productos para evitar N+1
        $cart->load(['items.product.category', 'items.product.primaryImage']);

        return $cart;
    }

    /**
     * Agrega un producto al carrito.
     *
     * Si el producto ya existe en el carrito, incrementa la cantidad.
     * Valida stock disponible antes de agregar.
     */
    public function addItem(?User $user, ?string $sessionId, int $productId, int $quantity = 1): Cart
    {
        $cart    = $this->getCart($user, $sessionId);
        $product = Product::findOrFail($productId);

        // Validar que el producto esté activo
        if (!$product->is_active) {
            throw new Exception('Este producto no está disponible.');
        }

        // Si el ítem ya existe, sumamos. Si no, lo creamos.
        $existingItem = $cart->items()->where('product_id', $productId)->first();

        $newQuantity = $existingItem
            ? $existingItem->quantity + $quantity
            : $quantity;

        // Validar stock
        if ($product->stock < $newQuantity) {
            throw new Exception(
                "Stock insuficiente para '{$product->name}'. " .
                "Disponible: {$product->stock}, solicitado: {$newQuantity}."
            );
        }

        if ($existingItem) {
            $existingItem->update(['quantity' => $newQuantity]);
        } else {
            $cart->items()->create([
                'product_id' => $productId,
                'quantity'   => $quantity,
            ]);
        }

        // Refresh para devolver los datos actualizados
        return $this->getCartWithDetails($user, $sessionId);
    }

    /**
     * Actualiza la cantidad de un ítem específico del carrito.
     *
     * Si la nueva cantidad es 0, elimina el ítem.
     */
    public function updateItemQuantity(?User $user, ?string $sessionId, int $productId, int $quantity): Cart
    {
        $cart = $this->getCart($user, $sessionId);

        if ($quantity <= 0) {
            return $this->removeItem($user, $sessionId, $productId);
        }

        $item = $cart->items()->where('product_id', $productId)->firstOrFail();

        // Validar stock
        $product = Product::findOrFail($productId);
        if ($product->stock < $quantity) {
            throw new Exception(
                "Stock insuficiente para '{$product->name}'. " .
                "Disponible: {$product->stock}, solicitado: {$quantity}."
            );
        }

        $item->update(['quantity' => $quantity]);

        return $this->getCartWithDetails($user, $sessionId);
    }

    /**
     * Elimina un ítem del carrito.
     */
    public function removeItem(?User $user, ?string $sessionId, int $productId): Cart
    {
        $cart = $this->getCart($user, $sessionId);

        $cart->items()->where('product_id', $productId)->delete();

        return $this->getCartWithDetails($user, $sessionId);
    }

    /**
     * Vacía todo el carrito.
     */
    public function clearCart(?User $user, ?string $sessionId): Cart
    {
        $cart = $this->getCart($user, $sessionId);

        $cart->items()->delete();

        return $this->getCartWithDetails($user, $sessionId);
    }

    /**
     * Transfiere los ítems del carrito de invitado al carrito del usuario.
     *
     * Escenario: el usuario navega sin cuenta, agrega productos al carrito,
     * y luego hace login. Sus ítems del carrito guest se mueven al user cart.
     *
     * Reglas:
     * - Si un producto ya existe en el carrito del usuario, se suma la cantidad
     * - Si no existe, se mueve directamente
     * - El carrito guest se borra al final
     */
    public function syncGuestCartToUser(string $sessionId, User $user): Cart
    {
        $guestCart = Cart::where('session_id', $sessionId)->first();

        // Si no hay carrito de invitado, devolvemos el del usuario
        if (!$guestCart || $guestCart->items->isEmpty()) {
            return $this->getCartWithDetails($user, null);
        }

        $userCart = $this->getCart($user, null);

        DB::transaction(function () use ($guestCart, $userCart) {
            foreach ($guestCart->items as $guestItem) {
                $existingItem = $userCart->items()
                    ->where('product_id', $guestItem->product_id)
                    ->first();

                if ($existingItem) {
                    // El producto ya estaba en el carrito del usuario → sumamos
                    $newQty = $existingItem->quantity + $guestItem->quantity;

                    // Respetar stock máximo
                    $stock = $guestItem->product->stock;
                    $existingItem->update([
                        'quantity' => min($newQty, $stock),
                    ]);
                } else {
                    // Mover el ítem al carrito del usuario
                    $guestItem->update(['cart_id' => $userCart->id]);
                }
            }

            // Borrar el carrito guest (los ítems restantes se borran por cascade)
            $guestCart->delete();
        });

        return $this->getCartWithDetails($user, null);
    }
}
