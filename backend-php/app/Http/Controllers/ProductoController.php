<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    // 1. LEER PRODUCTOS
    public function index()
    {
        return response()->json(Producto::all());
    }

    // 2. CREAR PRODUCTO (CON IMAGEN)
    public function store(Request $request) 
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'categoria' => 'required|string|max:100',
            'precio' => 'required|numeric|min:1',
            'stock_actual' => 'required|integer|min:0',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048' // Validamos foto
        ]);

        $datos = $request->all();

        // Si viene un archivo de foto, lo guardamos
        if ($request->hasFile('imagen')) {
            $ruta = $request->file('imagen')->store('productos', 'public');
            $datos['imagen'] = $ruta;
        }

        $producto = Producto::create($datos);
        return response()->json($producto, 201);
    }

    // 3. ACTUALIZAR PRODUCTO
    public function update(Request $request, $id)
    {
        $producto = Producto::find($id);
        
        if(!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $producto->update($request->all());
        return response()->json($producto);
    }

    // 4. ELIMINAR PRODUCTO
    public function destroy($id)
    {
        $producto = Producto::find($id);
        if($producto) {
            $producto->delete();
        }
        return response()->json(['message' => 'Producto eliminado']);
    }

    // 5. CAJA REGISTRADORA (DESCONTAR STOCK)
    public function procesarVenta(Request $request)
    {
        $carrito = $request->carrito;

        foreach ($carrito as $item) {
            $producto = Producto::find($item['id']);

            if ($producto && $producto->stock_actual >= $item['cantidad']) {
                $producto->stock_actual -= $item['cantidad'];
                $producto->save();
            } else {
                return response()->json(['error' => 'Stock insuficiente para ' . $producto->nombre], 400);
            }
        }

        return response()->json(['message' => 'Venta procesada con éxito y stock descontado']);
    }
}