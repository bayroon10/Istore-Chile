<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exports\ProductsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    public function exportProducts()
    {
        try {
            // Genera el archivo y fuerza la descarga
            return Excel::download(new ProductsExport, 'Inventario_iStore.xlsx');
        } catch (\Exception $e) {
            Log::error("[/api/reports/products] Error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno al generar el reporte de inventario en Excel.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}