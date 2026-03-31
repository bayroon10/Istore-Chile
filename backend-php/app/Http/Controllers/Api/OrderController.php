<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    // -------------------------------------------------------
    // ENDPOINTS DE CLIENTE
    // -------------------------------------------------------

    /**
     * POST /api/orders/checkout
     *
     * Convierte el carrito en una orden.
     * Requiere autenticación.
     *
     * Body:
     * {
     *   "shipping_name": "María González",
     *   "shipping_phone": "+56912345678",
     *   "shipping_street": "Av. Providencia 1234",
     *   "shipping_city": "Santiago",
     *   "shipping_region": "Región Metropolitana",
     *   "shipping_method": "Starken",
     *   "shipping_cost": 3990,
     *   "stripe_payment_id": "pi_xxx"  // Opcional, si ya se procesó el pago
     * }
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_name'     => 'required|string|max:255',
            'shipping_phone'    => 'required|string|max:20',
            'shipping_street'   => 'required|string|max:500',
            'shipping_city'     => 'required|string|max:100',
            'shipping_region'   => 'required|string|max:100',
            'shipping_method'   => 'required|string|max:50',
            'shipping_cost'     => 'integer|min:0',
            'discount'          => 'integer|min:0',
            'notes'             => 'nullable|string|max:1000',
        ]);

        try {
            $result = $this->orderService->createOrderFromCart(
                user: $request->user(),
                shippingData: $validated,
                paymentMethod: 'stripe',
            );

            return response()->json([
                'message'       => 'Orden creada exitosamente.',
                'client_secret' => $result['client_secret'],
                'data'          => new OrderResource($result['order']),
            ], 201);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/orders
     *
     * Devuelve el historial de órdenes del usuario autenticado.
     * Paginado: ?page=1&per_page=10
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 10);

        $orders = $this->orderService->getUserOrders(
            $request->user(),
            min($perPage, 50),  // Max 50 por página
        );

        return response()->json([
            'data' => OrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    /**
     * GET /api/orders/{id}
     *
     * Devuelve el detalle de una orden del usuario autenticado.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getUserOrder($request->user(), $id);

            return response()->json([
                'data' => new OrderResource($order),
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    // -------------------------------------------------------
    // ENDPOINTS DE ADMIN
    // -------------------------------------------------------

    /**
     * GET /api/admin/orders
     *
     * Lista todas las órdenes (admin). Filtra por estado: ?status=paid
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $status  = $request->string('status')->value() ?: null;

        $orders = $this->orderService->getAllOrders(
            min($perPage, 100),
            $status,
        );

        return response()->json([
            'data' => OrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    /**
     * PUT /api/admin/orders/{id}/status
     *
     * Actualiza el estado de una orden (admin).
     *
     * Body: { "status": "shipped" }
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:pending,paid,processing,shipped,delivered,cancelled',
        ]);

        try {
            $order = $this->orderService->updateOrderStatus($id, $request->status);

            return response()->json([
                'message' => "Estado actualizado a '{$order->status_label}'.",
                'data'    => new OrderResource($order),
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
