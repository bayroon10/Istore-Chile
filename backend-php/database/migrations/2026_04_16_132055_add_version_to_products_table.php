<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Añadimos la columna 'version' para implementar Optimistic Locking.
            // Esto permite manejar la concurrencia sin usar SELECT FOR UPDATE,
            // lo cual es necesario para la compatibilidad con PgBouncer en modo de pooling de transacciones.
            $table->unsignedInteger('version')->default(0)->after('sales_count');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
