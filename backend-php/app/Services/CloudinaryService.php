<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    /**
     * Sube una imagen a Cloudinary.
     * Retorna un arreglo con la URL y el public_id.
     */
    public function uploadImage(UploadedFile $file): array
    {
        // Se sube al folder configurado mediante preset u opcionalmente directo
        $result = $file->storeOnCloudinary();

        return [
            'image_url' => $result->getSecurePath(),
            'public_id' => $result->getPublicId(),
        ];
    }

    /**
     * Elimina una imagen de Cloudinary a partir de su public_id.
     */
    public function deleteImage(string $publicId): bool
    {
        try {
            cloudinary()->uploadApi()->destroy($publicId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
