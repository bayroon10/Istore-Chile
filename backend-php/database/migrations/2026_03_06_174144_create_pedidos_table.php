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
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('cliente_nombre');
            $table->string('cliente_email');
            $table->string('cliente_telefono');
            $table->string('direccion');
            $table->string('metodo_envio'); // Aquí irá Starken o Chilexpress
            $table->integer('total');
            $table->json('carrito'); // 🌟 TRUCO SENIOR: Guardamos toda la bolsa de compras aquí adentro
            $table->string('estado')->default('Pendiente'); // Para saber si ya lo enviaste o no
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
