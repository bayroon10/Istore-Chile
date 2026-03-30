<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exports\ProductsExport;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function exportProducts()
    {
        // Genera el archivo y fuerza la descarga
        return Excel::download(new ProductsExport, 'Inventario_iStore.xlsx');
    }
}