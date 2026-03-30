<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')
                  ->constrained()
                  ->cascadeOnDelete();   // Al borrar el carrito, se borran sus ítems
            $table->foreignId('product_id')
                  ->constrained()
                  ->cascadeOnDelete();  // Al borrar un producto, desaparece del carrito

            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            // Un producto no puede aparecer dos veces en el mismo carrito
            $table->unique(['cart_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
