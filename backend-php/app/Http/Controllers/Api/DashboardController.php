<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Obtiene las estadísticas generales del negocio para el dashboard.
     */
    public function index()
    {
        // 1. KPIs principales
        $totalRevenue = Order::whereIn('status', ['paid', 'shipped', 'delivered', 'processing'])
            ->sum('total');

        $totalOrders = Order::whereIn('status', ['paid', 'shipped', 'delivered', 'processing'])->count();
        
        $pendingOrders = Order::where('status', 'pending')->count();

        $activeProducts = Product::where('is_active', true);
        $lowStockCount = (clone $activeProducts)->where('stock', '<=', 5)->where('stock', '>', 0)->count();
        $outOfStockCount = (clone $activeProducts)->where('stock', 0)->count();

        // 2. Gráfico de ventas (últimos 7 días)
        $sevenDaysAgo = now()->subDays(7)->startOfDay();
        $chartData = Order::whereIn('status', ['paid', 'shipped', 'delivered', 'processing'])
            ->where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw('DATE(created_at) as date, SUM(total) as total_sales')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(fn($item) => [
                'fecha' => $item->date,
                'total_ventas' => (float) $item->total_sales
            ]);

        // 3. Órdenes recientes (las últimas 5)
        $recentOrders = Order::with(['user', 'items'])
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'kpis' => [
                'total_revenue' => (float) $totalRevenue,
                'total_orders'  => $totalOrders,
                'pending_orders' => $pendingOrders,
                'low_stock_alerts' => $lowStockCount + $outOfStockCount,
                'out_of_stock' => $outOfStockCount,
            ],
            'chart' => $chartData,
            'recent_orders' => OrderResource::collection($recentOrders),
        ]);
    }

    /**
     * Obtiene estadísticas específicas del almacén y valor de inventario.
     */
    public function warehouseStats()
    {
        $products = Product::all();
        
        $totalInventoryValue = $products->sum(function ($product) {
            return $product->price * $product->stock;
        });

        $totalStockUnits = $products->sum('stock');
        $activeProductsCount = $products->where('is_active', true)->count();
        
        $lowStockCount = $products->where('is_active', true)->where('stock', '<=', 5)->where('stock', '>', 0)->count();
        $outOfStockCount = $products->where('is_active', true)->where('stock', 0)->count();

        // Productos con mayor valor inmovilizado en stock
        $topValuableProducts = Product::where('stock', '>', 0)
            ->orderByRaw('(price * stock) DESC')
            ->take(5)
            ->get(['id', 'name', 'stock', 'price'])
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'stock' => $product->stock,
                    'price' => (float) $product->price,
                    'total_value' => (float) ($product->price * $product->stock)
                ];
            });

        return response()->json([
            'inventory_value' => (float) $totalInventoryValue,
            'total_stock_units' => $totalStockUnits,
            'active_products' => $activeProductsCount,
            'alerts' => [
                'low_stock' => $lowStockCount,
                'out_of_stock' => $outOfStockCount,
            ],
            'top_valuable_products' => $topValuableProducts,
        ]);
    }

    /**
     * Obtiene la tendencia de ventas (ingresos totales de los últimos 7 días).
     */
    public function salesTrend()
    {
        $sevenDaysAgo = now()->subDays(6)->startOfDay(); // últimos 7 días incluyendo hoy
        
        // Inicializar los últimos 7 días con ingresos en 0
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $englishDay = $date->format('l');
            $dayNames = [
                'Monday'    => 'Lun',
                'Tuesday'   => 'Mar',
                'Wednesday' => 'Mie',
                'Thursday'  => 'Jue',
                'Friday'    => 'Vie',
                'Saturday'  => 'Sab',
                'Sunday'    => 'Dom'
            ];
            $spanishDay = $dayNames[$englishDay] ?? $englishDay;
            
            $days[$date->format('Y-m-d')] = [
                'dia' => $spanishDay,
                'fecha' => $date->format('d/m'),
                'ingresos' => 0
            ];
        }

        // Obtener ventas reales agrupadas por día
        $sales = Order::whereIn('status', ['paid', 'shipped', 'delivered', 'processing', 'processing_payment'])
            ->where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw('DATE(created_at) as date, SUM(total) as total_sales')
            ->groupBy('date')
            ->get();

        foreach ($sales as $sale) {
            if (isset($days[$sale->date])) {
                $days[$sale->date]['ingresos'] = (float) $sale->total_sales;
            }
        }

        return response()->json(array_values($days));
    }

    /**
     * Obtiene los productos con stock crítico (menor o igual a 15).
     */
    public function criticalStock()
    {
        $products = Product::where('is_active', true)
            ->where('stock', '<=', 15)
            ->orderBy('stock', 'asc')
            ->take(10) // Limitado a los 10 más críticos para evitar sobrecargar el gráfico
            ->get(['name', 'stock']);

        $chartData = $products->map(function ($product) {
            return [
                'producto' => strlen($product->name) > 15 ? substr($product->name, 0, 12) . '...' : $product->name,
                'stock' => (int) $product->stock
            ];
        });

        return response()->json($chartData);
    }
}

