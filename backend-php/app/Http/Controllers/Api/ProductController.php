<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\CloudinaryService;
use Illuminate\Support\Arr;

class ProductController extends Controller
{
    protected CloudinaryService $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            Log::debug('Product listing request started.', [
                'has_search_filter' => $request->has('search'),
                'has_category_filter' => $request->has('category'),
            ]);

            $query = Product::query()
                ->with(['category', 'images', 'primaryImage'])
                ->where('is_active', true);

            // Búsqueda por nombre (Database Agnostic: Case Insensitive en MySQL/Postgres)
            if ($request->has('search')) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->search) . '%']);
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

            $perPage = max(1, min((int) $request->get('per_page', 12), 100));
            $products = $query->latest()->paginate($perPage);

            Log::debug('Product listing pagination completed.', [
                'total_products' => $products->total(),
                'items_count' => count($products->items()),
            ]);

            return ProductResource::collection($products);
        } catch (\Throwable $e) {
            Log::error('Product listing failed.', [
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Internal Server Error',
                'message' => 'No se pudo procesar la solicitud',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            $data = Arr::only($validated, ['name', 'category_id', 'price', 'compare_price', 'stock', 'description']);
            $data['slug'] = Str::slug($request->name) . '-' . uniqid();

            $product = Product::create($data);

            if ($request->hasFile('image')) {
                $uploadResult = $this->cloudinary->uploadImage($request->file('image'));
                $product->images()->create([
                    'image_url' => $uploadResult['image_url'],
                    'public_id' => $uploadResult['public_id'],
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
        $product = Product::with(['category', 'images', 'primaryImage'])
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

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id',
            'price' => 'sometimes|required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        return DB::transaction(function () use ($request, $product, $validated) {
            $data = Arr::only($validated, ['name', 'category_id', 'price', 'compare_price', 'stock', 'description']);
            
            if (isset($data['name'])) {
                $data['slug'] = Str::slug($data['name']) . '-' . $product->id;
            }

            $product->update($data);

            if ($request->hasFile('image')) {
                $uploadResult = $this->cloudinary->uploadImage($request->file('image'));
                
                // Marcar anteriores como no primarias
                $product->images()->update(['is_primary' => false]);
                
                $product->images()->create([
                    'image_url' => $uploadResult['image_url'],
                    'public_id' => $uploadResult['public_id'],
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
        
        // Eliminar imágenes en Cloudinary antes de borrar el producto
        foreach ($product->images as $image) {
            if ($image->public_id) {
                $this->cloudinary->deleteImage($image->public_id);
            }
        }
        
        // Soft delete visual o hard delete si se prefiere
        // El usuario pidió limpieza, así que borraremos el registro
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}
