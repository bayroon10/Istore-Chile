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
// 🔐 RUTAS AUTENTICADAS (Cualquier usuario con token)
// =============================================
// Estas rutas solo necesitan estar logueado, sin importar el rol.
// Aquí va lo que un cliente normal necesita acceder.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cliente/perfil', [ClienteAuthController::class, 'perfil']);
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

    // Gestión de Pedidos
    Route::get('/pedidos', [PedidoController::class, 'index']);
    Route::put('/pedidos/{id}', [PedidoController::class, 'update']);

    // Reportes
    Route::get('/reports/products', [ReportController::class, 'exportProducts']);
});
use Illuminate\Support\Facades\Artisan;

// RUTA SECRETA TEMPORAL PARA RESETEAR LA BD EN PRODUCCIÓN
Route::get('/reset-db-bairon-ninja', function () {
    try {
        Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true
        ]);
        return response()->json(['mensaje' => '¡Base de datos reseteada y con seeders en producción, mano!']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});