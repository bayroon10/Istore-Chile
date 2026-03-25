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

// 🌍 RUTAS PÚBLICAS (No necesitan Token)
Route::get('/productos', [ProductoController::class, 'index']);
Route::post('/pedidos', [PedidoController::class, 'store']);   
Route::post('/login', [AuthController::class, 'login']);        
Route::post('/webhook', [WebhookController::class, 'procesarWebhook']); 
Route::post('/chatbot', [ChatbotController::class, 'chat']);
Route::post('/cliente/registro', [ClienteAuthController::class, 'registro']);
Route::post('/cliente/login', [ClienteAuthController::class, 'login']);


// 🔒 RUTAS PROTEGIDAS (Solo el Admin con Token)
Route::middleware('auth:sanctum')->group(function () {
    // Gestión de Inventario
    Route::post('/productos', [ProductoController::class, 'store']);
    Route::put('/productos/{id}', [ProductoController::class, 'update']);
    Route::delete('/productos/{id}', [ProductoController::class, 'destroy']);
    
    
    // Gestión del Dashboard y Pedidos
    Route::get('/cliente/perfil', [ClienteAuthController::class, 'perfil']);
    Route::get('/estadisticas', [DashboardController::class, 'index']);
    Route::get('/pedidos', [PedidoController::class, 'index']); 
    Route::put('/pedidos/{id}', [PedidoController::class, 'update']); 
});