<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemAddon extends Model
{
    protected $fillable = [
        'order_item_id',
        'menu_addon_id',
        'addon_name',
        'addon_price',
    ];

    protected $casts = [
        'addon_price' => 'integer',
    ];

    /**
     * Order item yang memiliki addon ini.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Referensi ke menu addon asli.
     */
    public function menuAddon(): BelongsTo
    {
        return $this->belongsTo(MenuAddon::class);
    }
}
