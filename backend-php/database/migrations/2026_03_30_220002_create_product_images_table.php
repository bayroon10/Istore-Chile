<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                  ->constrained()
                  ->cascadeOnDelete();    // Si se borra el producto, se borran sus imágenes

            $table->string('url');                              // Ruta en storage o URL de Cloudinary
            $table->unsignedInteger('sort_order')->default(0); // Para ordenar las fotos
            $table->boolean('is_primary')->default(false);     // La foto principal del producto
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
