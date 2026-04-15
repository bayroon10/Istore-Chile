<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    protected CloudinaryService $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    /**
     * Sube una nueva imagen para un producto.
     */
    public function store(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        $request->validate([
            'image' => 'required|image|max:5120', // máximo 5MB
            'is_primary' => 'sometimes|boolean',
        ]);

        $uploadResult = $this->cloudinary->uploadImage($request->file('image'));
        
        $isPrimary = $request->boolean('is_primary');

        // Si la enviada es_primary, convertimos el resto a false
        if ($isPrimary) {
            $product->images()->update(['is_primary' => false]);
        }

        $productImage = $product->images()->create([
            'image_url' => $uploadResult['image_url'],
            'public_id' => $uploadResult['public_id'],
            'is_primary' => $isPrimary,
        ]);

        return response()->json([
            'message' => 'Imagen subida correctamente.',
            'image' => $productImage
        ], 201);
    }

    /**
     * Elimina una imagen.
     */
    public function destroy($productId, $imageId)
    {
        $product = Product::findOrFail($productId);
        $image = $product->images()->findOrFail($imageId);

        // Borrar de Cloudinary si tiene public_id
        if ($image->public_id) {
            $this->cloudinary->deleteImage($image->public_id);
        }

        $image->delete();

        return response()->json([
            'message' => 'Imagen eliminada correctamente.'
        ]);
    }
}
