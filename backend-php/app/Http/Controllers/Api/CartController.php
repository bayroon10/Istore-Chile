<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Auth\AuthenticationException;

class CartController extends Controller
{
    public function __construct(
        private CartService $cartService,
    ) {}

    // -------------------------------------------------------
    // Helpers internos
    // -------------------------------------------------------

    /**
     * Extrae el usuario autenticado (o null) y el session_id del request.
     * El session_id viene como header `X-Session-Id` desde el frontend
     * para identificar carritos de invitados.
     */
    private function resolveIdentity(Request $request): array
    {
        $user = null;

        try {
            $user = $request->user('sanctum');
        } catch (AuthenticationException $e) {
            $user = null;
        }

        $sessionId = $request->header('X-Session-Id');

        if (!$user && (!$sessionId || !\Illuminate\Support\Str::isUuid($sessionId))) {
            throw new \InvalidArgumentException('El header X-Session-Id es requerido y debe tener un formato UUIDv4 válido.');
        }

        return [
            'user'       => $user,
            'session_id' => $sessionId,
        ];
    }

    /**
     * Respuesta estandarizada del carrito.
     */
    private function cartResponse(mixed $cart, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => new CartResource($cart),
        ], $status);
    }

    private function errorResponse(Exception $e): JsonResponse
    {
        if ($e instanceof \InvalidArgumentException) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        Log::warning('Cart request could not be processed.', [
            'exception_class' => $e::class,
            'exception_message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ]);

        return response()->json(['error' => 'No se pudo procesar la solicitud'], 400);
    }

    // -------------------------------------------------------
    // Endpoints
    // -------------------------------------------------------

    /**
     * GET /api/cart
     *
     * Devuelve el carrito actual del usuario o invitado.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            ['user' => $user, 'session_id' => $sessionId] = $this->resolveIdentity($request);
            $cart = $this->cartService->getCartWithDetails($user, $sessionId);
            return $this->cartResponse($cart);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * POST /api/cart/items
     *
     * Agrega un producto al carrito.
     *
     * Body: { "product_id": 1, "quantity": 2 }
     */
    public function addItem(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity'    => 'integer|min:1',
        ]);

        try {
            ['user' => $user, 'session_id' => $sessionId] = $this->resolveIdentity($request);
            $cart = $this->cartService->addItem(
                $user,
                $sessionId,
                $request->product_id,
                $request->quantity ?? 1,
            );

            return $this->cartResponse($cart, 201);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * PUT /api/cart/items/{productId}
     *
     * Actualiza la cantidad de un ítem. Si quantity es 0, lo elimina.
     *
     * Body: { "quantity": 3 }
     */
    public function updateItem(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        try {
            ['user' => $user, 'session_id' => $sessionId] = $this->resolveIdentity($request);
            $cart = $this->cartService->updateItemQuantity(
                $user,
                $sessionId,
                $productId,
                $request->quantity,
            );

            return $this->cartResponse($cart);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * DELETE /api/cart/items/{productId}
     *
     * Elimina un ítem del carrito.
     */
    public function removeItem(Request $request, int $productId): JsonResponse
    {
        try {
            ['user' => $user, 'session_id' => $sessionId] = $this->resolveIdentity($request);
            $cart = $this->cartService->removeItem($user, $sessionId, $productId);
            return $this->cartResponse($cart);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * DELETE /api/cart
     *
     * Vacía todo el carrito.
     */
    public function clear(Request $request): JsonResponse
    {
        try {
            ['user' => $user, 'session_id' => $sessionId] = $this->resolveIdentity($request);
            $cart = $this->cartService->clearCart($user, $sessionId);
            return $this->cartResponse($cart);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * POST /api/cart/sync
     *
     * Transfiere el carrito de invitado al usuario autenticado (post-login).
     * El frontend envía el session_id que usaba como invitado.
     *
     * Body: { "session_id": "abc-123-uuid" }
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string|uuid',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Debes estar autenticado para sincronizar el carrito.'], 401);
        }

        try {
            $cart = $this->cartService->syncGuestCartToUser(
                $user,
                $request->session_id,
            );

            return $this->cartResponse($cart);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }
}
