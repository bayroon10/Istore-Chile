<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Calculamos los KPIs (Indicadores Clave de Rendimiento)
        $totalIngresos = Pedido::sum('total');
        $totalPedidos = Pedido::count();
        $productosBajoStock = Producto::where('stock_actual', '<=', 5)->count();

        // 2. Preparamos los datos para el gráfico de React (Ventas agrupadas por fecha)
        $ventasPorDia = Pedido::selectRaw('DATE(created_at) as fecha, SUM(total) as total_ventas')
            ->groupBy('fecha')
            ->orderBy('fecha', 'asc')
            ->take(7)
            ->get();

        return response()->json([
            'kpis' => [
                'ingresos' => $totalIngresos,
                'pedidos' => $totalPedidos,
                'alertas_stock' => $productosBajoStock
            ],
            'grafico' => $ventasPorDia
        ]);
    }
}