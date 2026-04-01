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

Route::get('/magia-admin-bairon', function () {
    try {
        // 1. Aseguramos el Admin con el role correcto
        $user = \App\Models\User::updateOrCreate(
            ['email' => 'admin@istore.com'],
            [
                'name'     => 'Admin iStore',
                'password' => \Illuminate\Support\Facades\Hash::make('12345678'),
                'role'     => 'admin',
            ]
        );

        // 2. Poblar Categorías (3. 'iPhone', 'MacBook', 'Accesorios')
        $categoriasInfo = [
            ['name' => 'iPhone', 'slug' => 'iphone'],
            ['name' => 'MacBook', 'slug' => 'macbook'],
            ['name' => 'Accesorios', 'slug' => 'accesorios'],
        ];
        
        $categorias = [];
        foreach ($categoriasInfo as $catData) {
            $categorias[$catData['slug']] = \App\Models\Category::firstOrCreate(
                ['slug' => $catData['slug']],
                ['name' => $catData['name'], 'is_active' => true]
            );
        }

        // 3. Poblar Productos (6 productos)
        $productosInfo = [
            [
                'name' => 'iPhone 15 Pro Max',
                'description' => 'El iPhone más avanzado con titanio.',
                'price' => 1299990,
                'stock' => 15,
                'category_slug' => 'iphone',
                'image' => 'https://via.placeholder.com/400?text=iPhone+15+Pro+Max'
            ],
            [
                'name' => 'iPhone 14 Pro',
                'description' => 'Cámara de 48MP y Dynamic Island.',
                'price' => 999990,
                'stock' => 10,
                'category_slug' => 'iphone',
                'image' => 'https://via.placeholder.com/400?text=iPhone+14+Pro'
            ],
            [
                'name' => 'MacBook Pro 16" M3 Max',
                'description' => 'Potencia bestial para creadores.',
                'price' => 3499990,
                'stock' => 5,
                'category_slug' => 'macbook',
                'image' => 'https://via.placeholder.com/400?text=MacBook+Pro+16'
            ],
            [
                'name' => 'MacBook Air 15" M2',
                'description' => 'Pantalla Liquid Retina expansiva.',
                'price' => 1499990,
                'stock' => 20,
                'category_slug' => 'macbook',
                'image' => 'https://via.placeholder.com/400?text=MacBook+Air+15'
            ],
            [
                'name' => 'AirPods Pro 2',
                'description' => 'Cancelación activa de ruido al doble.',
                'price' => 249990,
                'stock' => 30,
                'category_slug' => 'accesorios',
                'image' => 'https://via.placeholder.com/400?text=AirPods+Pro+2'
            ],
            [
                'name' => 'MagSafe Charger',
                'description' => 'Carga inalámbrica rápida y sencilla.',
                'price' => 49990,
                'stock' => 50,
                'category_slug' => 'accesorios',
                'image' => 'https://via.placeholder.com/400?text=MagSafe+Charger'
            ],
        ];

        $productosCreados = 0;
        foreach ($productosInfo as $prodData) {
            $cat = $categorias[$prodData['category_slug']];
            $slug = \Illuminate\Support\Str::slug($prodData['name']);

            $product = \App\Models\Product::firstOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $cat->id,
                    'name' => $prodData['name'],
                    'description' => $prodData['description'],
                    'price' => $prodData['price'],
                    'stock' => $prodData['stock'],
                    'is_active' => true,
                ]
            );
            $productosCreados++;

            // 4. Imagen principal de placeholder garantizada
            \App\Models\ProductImage::firstOrCreate(
                ['product_id' => $product->id, 'is_primary' => true],
                ['url' => $prodData['image']]
            );
        }

        return response()->json([
            'mensaje' => '¡CORONAMOS MANO! Admin creado y Catálogo poblado con éxito.',
            'admin' => $user->email,
            'categorias_agregadas' => count($categoriasInfo),
            'productos_agregados' => $productosCreados,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});
