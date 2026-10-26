<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom untuk hasil charge langsung (Core API): QRIS & VA bank.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('va_number')->nullable()->after('fraud_status');
            $table->string('va_bank')->nullable()->after('va_number');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['va_number', 'va_bank']);
        });
    }
};
