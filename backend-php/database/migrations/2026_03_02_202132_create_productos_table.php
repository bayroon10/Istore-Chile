<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');                     // Ej: AirPods Pro 2, Cargador Tipo C 20W
            $table->string('categoria');                  // Ej: Audífonos, Cargadores, Smartwatches
            $table->integer('precio');                    // Ej: 25000 (Precio de venta)
            $table->integer('stock_actual')->default(0);  // Cuántos tienes en la tienda
            $table->string('compatibilidad')->nullable(); // Ej: iPhone 15, Universal (Alexa)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
