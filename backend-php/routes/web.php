<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductoController; // <-- 1. Importamos el repartidor aquí arriba
Route::get('/', function () {
    return view('welcome');
});
Route::get('/tienda/productos', [ProductoController::class, 'index']);
Route::post('/tienda/productos', [App\Http\Controllers\ProductoController::class, 'store']);
Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
    return '¡Base de datos formateada y Admin sembrado con éxito! 🚀';
});