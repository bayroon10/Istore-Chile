<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // En PostgreSQL enum() se materializa como varchar + CHECK constraint: se reemplaza.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check
                CHECK (status IN ('draft','pending','paid','processing','shipped','delivered','cancelled'))");
        } else {
            // SQLite (tests): rebuild nativo de Laravel 12, el CHECK inline se descarta.
            Schema::table('orders', function (Blueprint $table) {
                $table->string('status', 20)->default('pending')->change();
            });
        }

        // Los drafts representan hechos de envío y pago todavía desconocidos.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_name')->nullable()->change();
            $table->string('shipping_phone')->nullable()->change();
            $table->string('shipping_street')->nullable()->change();
            $table->string('shipping_city')->nullable()->change();
            $table->string('shipping_region')->nullable()->change();
            $table->string('shipping_method')->nullable()->change();
            $table->string('payment_method')->nullable()->default(null)->change();

            $table->uuid('draft_request_id')->nullable()->after('status');
            $table->timestamp('draft_expires_at')->nullable()->after('draft_request_id');

            $table->index(['status', 'draft_expires_at'], 'orders_status_expires_index');
        });

        // Idempotencia por cliente: solo aplica cuando existe un identificador de solicitud.
        DB::statement('CREATE UNIQUE INDEX orders_user_draft_request_unique
            ON orders (user_id, draft_request_id) WHERE draft_request_id IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS orders_user_draft_request_unique');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_expires_index');
            $table->dropColumn(['draft_request_id', 'draft_expires_at']);
        });

        // Intencionalmente no se restauran NOT NULL: podrían permanecer drafts existentes.
    }
};
