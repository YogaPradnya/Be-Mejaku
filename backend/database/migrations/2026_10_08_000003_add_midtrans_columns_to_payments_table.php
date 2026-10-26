<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom Midtrans Snap ke tabel payments.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('snap_token')->nullable()->after('qr_data');
            $table->text('redirect_url')->nullable()->after('snap_token');
            $table->string('midtrans_order_id')->nullable()->unique()->after('redirect_url');
            $table->string('transaction_id')->nullable()->after('midtrans_order_id');
            $table->string('payment_type')->nullable()->after('transaction_id');
            $table->string('fraud_status')->nullable()->after('payment_type');
            $table->json('midtrans_response')->nullable()->after('fraud_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'snap_token',
                'redirect_url',
                'midtrans_order_id',
                'transaction_id',
                'payment_type',
                'fraud_status',
                'midtrans_response',
            ]);
        });
    }
};
