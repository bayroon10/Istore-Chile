<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('product_id')
                  ->nullable()          // nullable: si el producto se borra, conservamos el ítem
                  ->constrained()
                  ->nullOnDelete();

            // Snapshot del producto al momento de la compra
            // Esto es CRÍTICO: si el admin cambia el precio, el historial no se altera
            $table->string('product_name');             // Nombre al momento de comprar
            $table->unsignedInteger('product_price');   // Precio al momento de comprar
            $table->string('product_image')->nullable(); // Imagen al momento de comprar

            $table->unsignedInteger('quantity');
            $table->unsignedInteger('subtotal');        // product_price * quantity

            $table->enum('fulfillment_status', [
                'pending',
                'shipped',
                'delivered',
            ])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
