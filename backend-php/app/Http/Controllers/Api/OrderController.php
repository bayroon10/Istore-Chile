<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    private function errorResponse(Exception $e, int $status): JsonResponse
    {
        Log::warning('Order request could not be processed.', [
            'exception_class' => $e::class,
            'exception_message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ]);

        return response()->json(['error' => 'No se pudo procesar la solicitud'], $status);
    }

    // -------------------------------------------------------
    // ENDPOINTS DE CLIENTE
    // -------------------------------------------------------

    /**
     * POST /api/orders/checkout
     *
     * Convierte el carrito en una orden. Requiere autenticación y el header
     * Idempotency-Key con un UUID v4; la clave nunca se admite en el body.
     */
    public function checkout(Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        $validated = $request->validate([
            'shipping_name' => 'required|string|max:255',
            'shipping_phone' => 'required|string|max:20',
            'shipping_street' => 'required|string|max:500',
            'shipping_city' => 'required|string|max:100',
            'shipping_region' => 'required|string|max:100',
            'shipping_method' => 'required|string|in:Starken,Chilexpress,Retiro',
            'notes' => 'nullable|string|max:1000',
            'idempotency_key' => 'prohibited',
            'checkout_idempotency_key' => 'prohibited',
            'idempotencyKey' => 'prohibited',
            'Idempotency-Key' => 'prohibited',
        ]);

        if (! is_string($idempotencyKey) || ! preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',
            $idempotencyKey,
        )) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'Idempotency-Key' => ['El encabezado Idempotency-Key debe ser un UUID v4 válido.'],
            ]);
        }

        try {
            $result = $this->orderService->createOrderFromCart(
                user: $request->user(),
                shippingData: $validated,
                idempotencyKey: $idempotencyKey,
                paymentMethod: 'stripe',
            );

            return response()->json([
                'message' => $result['replayed']
                    ? 'Orden recuperada exitosamente.'
                    : 'Orden creada exitosamente.',
                'client_secret' => $result['client_secret'],
                'data' => new OrderResource($result['order']),
            ], $result['replayed'] ? 200 : 201);
        } catch (Exception $e) {
            return $this->errorResponse($e, 400);
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
            return $this->errorResponse($e, 404);
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
            return $this->errorResponse($e, 400);
        }
    }
}
