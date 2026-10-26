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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // contoh: MJK-72600-BA
            $table->foreignId('table_id')->constrained()->onDelete('restrict');
            $table->string('notes', 150)->nullable();
            $table->integer('subtotal');
            $table->integer('service_fee');
            $table->integer('tax');
            $table->integer('discount')->default(0);
            $table->integer('total');
            $table->string('promo_code')->nullable();
            $table->string('delivery_type')->default('dine_in'); // dine_in atau delivery
            $table->string('payment_status')->default('pending'); // pending, checking, paid, failed, expired
            $table->string('preparation_status')->default('received'); // received, preparing, ready, completed
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
