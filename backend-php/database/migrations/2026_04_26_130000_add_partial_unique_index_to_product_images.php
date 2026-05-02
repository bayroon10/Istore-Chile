<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // En PostgreSQL, esto asegura que solo haya UN is_primary = true por product_id
        // Previene condiciones de carrera donde múltiples inserciones intentan ser "primary" simultáneamente
        DB::statement('CREATE UNIQUE INDEX unique_primary_image_per_product ON product_images (product_id) WHERE (is_primary = true)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unique_primary_image_per_product');
    }
};
