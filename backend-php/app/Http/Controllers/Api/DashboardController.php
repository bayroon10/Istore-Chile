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

        $totalOrders = Order::count();
        
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
}
