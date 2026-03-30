<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                  ->constrained()
                  ->restrictOnDelete();

            $table->string('name');
            $table->string('slug')->unique();            // Auto-generado desde el modelo
            $table->text('description')->nullable();
            $table->unsignedInteger('price');            // Precio en pesos sin decimales (CLP)
            $table->unsignedInteger('compare_price')     // Precio anterior tachado (opcional)
                  ->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->string('sku')->unique()->nullable(); // Stock Keeping Unit
            $table->string('compatibility')->nullable(); // Ej: iPhone 15, Universal

            // Campos de visibilidad y destacado
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            // Campos de métricas (desnormalizados para performance)
            $table->decimal('rating_avg', 3, 2)->default(0.00);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->unsignedInteger('sales_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
