<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\ClienteAuthController;



// =============================================
// 🌍 RUTAS PÚBLICAS (No necesitan Token)
// =============================================
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{idOrSlug}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/webhooks/stripe', [WebhookController::class, 'handle']);
Route::post('/chatbot', [ChatbotController::class, 'chat']);
Route::post('/cliente/registro', [ClienteAuthController::class, 'registro']);
Route::post('/cliente/login', [ClienteAuthController::class, 'login']);

// =============================================
// 🛒 CARRITO (Autenticado - Temporalmente para el test)
// =============================================
Route::middleware('auth:sanctum')->prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'show']);
    Route::post('/items', [CartController::class, 'addItem']);
    Route::put('/items/{productId}', [CartController::class, 'updateItem']);
    Route::delete('/items/{productId}', [CartController::class, 'removeItem']);
    Route::delete('/', [CartController::class, 'clear']);
});

// Sync requiere autenticación
Route::middleware('auth:sanctum')->post('/cart/sync', [CartController::class, 'sync']);

// =============================================
// 🔐 RUTAS AUTENTICADAS (Cualquier usuario con token)
// =============================================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cliente/perfil', [ClienteAuthController::class, 'perfil']);

    // Órdenes del cliente
    Route::post('/orders/checkout', [OrderController::class, 'checkout']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
});

// =============================================
// 🛡️ RUTAS DE ADMINISTRADOR (Token + Rol Admin)
// =============================================
Route::middleware(['auth:sanctum', 'admin'])->group(function () {

    // Gestión de Inventario (CRUD de productos)
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // Imágenes de Producto
    Route::post('/products/{id}/images', [ProductImageController::class, 'store']);
    Route::delete('/products/{id}/images/{imageId}', [ProductImageController::class, 'destroy']);

    // Gestión de Categorías
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Dashboard y Estadísticas
    Route::get('/estadisticas', [DashboardController::class, 'index']);

    // Gestión de Órdenes
    Route::get('/admin/orders', [OrderController::class, 'adminIndex']);
    Route::put('/admin/orders/{id}/status', [OrderController::class, 'updateStatus']);

    // Reportes
    Route::get('/reports/products', [ReportController::class, 'exportProducts']);
});



