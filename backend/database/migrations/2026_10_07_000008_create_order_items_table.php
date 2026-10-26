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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('menu_item_id')->constrained()->onDelete('restrict');
            $table->foreignId('menu_portion_id')->nullable()->constrained()->onDelete('restrict');
            $table->foreignId('spice_level_id')->nullable()->constrained()->onDelete('restrict');
            $table->integer('quantity')->default(1);
            $table->integer('unit_price'); // harga satuan setelah kalkulasi
            $table->integer('line_total'); // unit_price * quantity
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
