<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Http\Requests\StoreImageRequest;
use App\Services\CloudinaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductImageController extends Controller
{
    protected CloudinaryService $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    /**
     * Sube una nueva imagen con resiliencia (compensación) y bloqueo pesimista.
     */
    public function store(StoreImageRequest $request, $productId)
    {
        $product = Product::findOrFail($productId);

        // 1. Subida a Cloudinary (Llamada externa fuera de transacción)
        $uploadResult = $this->cloudinary->uploadImage($request->file('image'));
        $isPrimary = $request->boolean('is_primary');

        try {
            // 2. Transacción de Base de Datos con Bloqueo Pesimista
            $productImage = DB::transaction(function () use ($product, $uploadResult, $isPrimary) {
                if ($isPrimary) {
                    // Bloqueamos los registros actuales del producto para evitar race conditions
                    ProductImage::where('product_id', $product->id)
                        ->lockForUpdate()
                        ->update(['is_primary' => false]);
                }

                return $product->images()->create([
                    'image_url' => $uploadResult['image_url'],
                    'public_id' => $uploadResult['public_id'],
                    'is_primary' => $isPrimary,
                ]);
            });

            return response()->json([
                'message' => 'Imagen procesada con éxito.',
                'image' => $productImage
            ], 201);

        } catch (\Exception $e) {
            // 3. Limpieza Compensatoria con Manejo de Doble Fallo (Double Fault)
            try {
                $this->cloudinary->deleteImage($uploadResult['public_id']);
            } catch (\Exception $cloudinaryException) {
                // Si incluso el borrado compensatorio falla, marcamos como crítico para limpieza manual
                Log::critical("ASSET HUÉRFANO EN CLOUDINARY: No se pudo eliminar tras fallo en DB.", [
                    'public_id' => $uploadResult['public_id'],
                    'db_error'  => $e->getMessage(),
                    'cloudinary_error' => $cloudinaryException->getMessage()
                ]);
            }

            Log::error("Fallo de sincronización DB: " . $e->getMessage(), [
                'product_id' => $productId,
                'public_id'  => $uploadResult['public_id']
            ]);

            return response()->json([
                'message' => 'Error crítico de sincronización.',
                'error'   => 'La operación fue revertida localmente, pero ocurrió un error en el proveedor externo.'
            ], 500);
        }
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
