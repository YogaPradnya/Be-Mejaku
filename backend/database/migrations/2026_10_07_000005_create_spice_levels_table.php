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
        Schema::create('spice_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('level'); // 0 = tidak pedas, 1 = sedang, 2 = pedas, 3 = sangat pedas
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spice_levels');
    }
};
