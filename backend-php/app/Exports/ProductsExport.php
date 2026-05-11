<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // Traemos los productos junto con su relación de categoría para evitar N+1 queries
        return Product::with('category')->get();
    }

    /**
    * Mapea cada fila para exportar las columnas deseadas.
    */
    public function map($product): array
    {
        return [
            $product->id,
            $product->name,
            $product->category ? $product->category->name : 'Sin Categoría',
            (float) $product->price,
            $product->stock,
        ];
    }

    /**
    * Encabezados del archivo Excel
    */
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