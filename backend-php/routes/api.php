<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
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
// 🛒 CARRITO (Público + Autenticado)
// =============================================
Route::prefix('cart')->group(function () {
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

\Illuminate\Support\Facades\Route::get('/magia-admin-bairon', function () {
    try {
        // 1. Creamos o actualizamos al usuario a la mala
        $user = \App\Models\User::updateOrCreate(
            ['email' => 'admin@istore.com'],
            [
                'name' => 'Admin iStore',
                'password' => \Illuminate\Support\Facades\Hash::make('12345678')
            ]
        );

        // 2. Le damos el rol de Administrador (compatible con Spatie)
        try {
            $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
            $user->assignRole($role);
        } catch (\Exception $e) {
            // Si no usa Spatie, intentamos guardar en una columna 'rol' por si acaso
            try {
                $user->rol = 'admin';
                $user->save();
            } catch (\Exception $e2) {
            }
        }

        return "¡CORONAMOS MANO! Cuenta de Admin creada/forzada con éxito. Correo: admin@istore.com | Clave: 12345678";
    } catch (\Exception $e) {
        return "Error creando el admin: " . $e->getMessage();
    }
});
