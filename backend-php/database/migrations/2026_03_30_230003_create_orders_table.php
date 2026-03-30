<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Número legible para el cliente: IST-20260330-0001
            $table->string('order_number')->unique();

            $table->foreignId('user_id')
                  ->nullable()         // nullable en caso de checkout como invitado
                  ->constrained()
                  ->nullOnDelete();

            // Snapshot de la dirección al momento de comprar
            // (no FK directa, para que si el usuario borra su dirección el pedido no se pierda)
            $table->string('shipping_name');
            $table->string('shipping_phone');
            $table->string('shipping_street');
            $table->string('shipping_city');
            $table->string('shipping_region');
            $table->string('shipping_method');        // Starken, Chilexpress, Retiro

            // Estado del pedido
            $table->enum('status', [
                'pending',      // Pago pendiente
                'paid',         // Pago confirmado
                'processing',   // Preparando
                'shipped',      // Enviado
                'delivered',    // Entregado
                'cancelled',    // Cancelado
            ])->default('pending');

            // Montos (en pesos CLP, sin decimales)
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('shipping_cost')->default(0);
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('total');

            // Pago
            $table->string('payment_method')->default('stripe');
            $table->string('stripe_payment_id')->nullable();

            $table->text('notes')->nullable();

            // Timestamps de ciclo de vida
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
