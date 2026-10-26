<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MejaKu hanya memiliki satu toko, sehingga lokasi/cabang dan area
     * tidak lagi disimpan. Cukup kode meja dan lantai.
     */
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn(['location', 'area']);
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->string('location')->nullable();
            $table->string('area')->nullable();
        });
    }
};
