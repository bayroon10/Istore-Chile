<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('checkout_idempotency_key')->nullable();
            $table->string('currency', 3)->default('clp');
        });

        DB::statement('CREATE UNIQUE INDEX orders_user_checkout_idempotency_unique
            ON orders (user_id, checkout_idempotency_key)
            WHERE user_id IS NOT NULL AND checkout_idempotency_key IS NOT NULL');

        DB::statement('CREATE UNIQUE INDEX orders_stripe_payment_id_unique
            ON orders (stripe_payment_id)
            WHERE stripe_payment_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS orders_user_checkout_idempotency_unique');
        DB::statement('DROP INDEX IF EXISTS orders_stripe_payment_id_unique');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['checkout_idempotency_key', 'currency']);
        });
    }
};
