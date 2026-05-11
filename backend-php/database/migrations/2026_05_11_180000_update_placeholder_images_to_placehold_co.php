<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Actualiza las imágenes de via.placeholder.com a placehold.co en la tabla product_images
        DB::table('product_images')
            ->where('image_url', 'like', '%via.placeholder.com%')
            ->update([
                'image_url' => DB::raw("REPLACE(image_url, 'https://via.placeholder.com/', 'https://placehold.co/')")
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('product_images')
            ->where('image_url', 'like', '%placehold.co%')
            ->update([
                'image_url' => DB::raw("REPLACE(image_url, 'https://placehold.co/', 'https://via.placeholder.com/')")
            ]);
    }
};
