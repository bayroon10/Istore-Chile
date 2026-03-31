<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['category', 'images'])
            ->where('is_active', true);

        // Búsqueda por nombre
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Filtro por categoría (ID o Slug)
        if ($request->has('category')) {
            $category = $request->category;
            $query->whereHas('category', function ($q) use ($category) {
                if (is_numeric($category)) {
                    $q->where('id', $category);
                } else {
                    $q->where('slug', $category);
                }
            });
        }

        $products = $query->latest()->paginate($request->get('per_page', 12));

        return ProductResource::collection($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        return DB::transaction(function () use ($request) {
            $data = $request->all();
            $data['slug'] = Str::slug($request->name) . '-' . uniqid();

            $product = Product::create($data);

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('products', 'public');
                $product->images()->create([
                    'url' => $path,
                    'is_primary' => true,
                ]);
            }

            return new ProductResource($product->load(['category', 'images']));
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $idOrSlug)
    {
        $product = Product::with(['category', 'images'])
            ->where('id', $idOrSlug)
            ->orWhere('slug', $idOrSlug)
            ->firstOrFail();

        return new ProductResource($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id',
            'price' => 'sometimes|required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        return DB::transaction(function () use ($request, $product) {
            $data = $request->all();
            
            if ($request->has('name')) {
                $data['slug'] = Str::slug($request->name) . '-' . $product->id;
            }

            $product->update($data);

            if ($request->hasFile('image')) {
                // Eliminar imágenes anteriores opcionalmente o solo agregar una nueva primaria
                // Por ahora, agregamos como nueva primaria
                $path = $request->file('image')->store('products', 'public');
                
                // Marcar anteriores como no primarias
                $product->images()->update(['is_primary' => false]);
                
                $product->images()->create([
                    'url' => $path,
                    'is_primary' => true,
                ]);
            }

            return new ProductResource($product->fresh(['category', 'images']));
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);
        
        // Soft delete visual o hard delete si se prefiere
        // El usuario pidió limpieza, así que borraremos el registro
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}
