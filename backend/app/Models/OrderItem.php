<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'menu_item_id',
        'menu_portion_id',
        'spice_level_id',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'line_total' => 'integer',
    ];

    /**
     * Pesanan yang memiliki item ini.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Menu item yang dipesan.
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * Porsi yang dipilih.
     */
    public function portion(): BelongsTo
    {
        return $this->belongsTo(MenuPortion::class, 'menu_portion_id');
    }

    /**
     * Tingkat pedas yang dipilih.
     */
    public function spiceLevel(): BelongsTo
    {
        return $this->belongsTo(SpiceLevel::class);
    }

    /**
     * Addon yang dipilih untuk item ini.
     */
    public function addons(): HasMany
    {
        return $this->hasMany(OrderItemAddon::class);
    }
}
