<?php

namespace App\Exports;

use App\Models\Producto;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductsExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // Traemos solo las columnas que usas en tu frontend
        return Producto::select('id', 'nombre', 'categoria', 'precio', 'stock_actual')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre del Producto',
            'Categoría',
            'Precio ($)',
            'Stock Actual',
        ];
    }
}