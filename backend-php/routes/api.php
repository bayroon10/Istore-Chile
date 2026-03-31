<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ClienteAuthController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;

// =============================================
// 🌍 RUTAS PÚBLICAS (No necesitan Token)
// =============================================
Route::get('/productos', [ProductoController::class, 'index']);
Route::post('/pedidos', [PedidoController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/webhook', [WebhookController::class, 'procesarWebhook']);
Route::post('/chatbot', [ChatbotController::class, 'chat']);
Route::post('/cliente/registro', [ClienteAuthController::class, 'registro']);
Route::post('/cliente/login', [ClienteAuthController::class, 'login']);

// =============================================
// 🛒 CARRITO (Público + Autenticado)
// =============================================
// Estas rutas funcionan TANTO para invitados (X-Session-Id header)
// como para usuarios autenticados (Bearer token).
//
// Usamos sanctum como middleware OPCIONAL para que $request->user()
// esté disponible cuando hay token, pero no bloquee sin él.
Route::prefix('cart')->group(function () {
    Route::get('/',                [CartController::class, 'show']);
    Route::post('/items',          [CartController::class, 'addItem']);
    Route::put('/items/{productId}', [CartController::class, 'updateItem']);
    Route::delete('/items/{productId}', [CartController::class, 'removeItem']);
    Route::delete('/',             [CartController::class, 'clear']);
});

// Sync requiere autenticación (solo hay user post-login)
Route::middleware('auth:sanctum')->post('/cart/sync', [CartController::class, 'sync']);

// =============================================
// 🔐 RUTAS AUTENTICADAS (Cualquier usuario con token)
// =============================================
// Estas rutas solo necesitan estar logueado, sin importar el rol.
// Aquí va lo que un cliente normal necesita acceder.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cliente/perfil', [ClienteAuthController::class, 'perfil']);

    // Órdenes del cliente
    Route::post('/orders/checkout', [OrderController::class, 'checkout']);
    Route::get('/orders',           [OrderController::class, 'index']);
    Route::get('/orders/{id}',      [OrderController::class, 'show']);
});

// =============================================
// 🛡️ RUTAS DE ADMINISTRADOR (Token + Rol Admin)
// =============================================
// Estas rutas requieren:
//   1. Estar autenticado (auth:sanctum)
//   2. Tener rol 'admin' (middleware 'admin')
//
// Si un cliente intenta acceder con su token, recibirá un 403.
Route::middleware(['auth:sanctum', 'admin'])->group(function () {

    // Gestión de Inventario (CRUD de productos)
    Route::post('/productos', [ProductoController::class, 'store']);
    Route::put('/productos/{id}', [ProductoController::class, 'update']);
    Route::delete('/productos/{id}', [ProductoController::class, 'destroy']);

    // Dashboard y Estadísticas
    Route::get('/estadisticas', [DashboardController::class, 'index']);

    // Gestión de Órdenes (nuevo sistema)
    Route::get('/admin/orders',              [OrderController::class, 'adminIndex']);
    Route::put('/admin/orders/{id}/status',  [OrderController::class, 'updateStatus']);

    // Gestión de Pedidos (legacy — será eliminado)
    Route::get('/pedidos', [PedidoController::class, 'index']);
    Route::put('/pedidos/{id}', [PedidoController::class, 'update']);

    // Reportes
    Route::get('/reports/products', [ReportController::class, 'exportProducts']);
});
