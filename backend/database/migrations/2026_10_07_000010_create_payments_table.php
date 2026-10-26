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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('method')->default('qris'); // qris, bank_transfer, e_wallet
            $table->string('payment_code')->nullable(); // kode pembayaran
            $table->text('qr_data')->nullable(); // data QR untuk QRIS
            $table->integer('amount'); // nominal pembayaran
            $table->string('status')->default('pending'); // pending, paid, failed, expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
