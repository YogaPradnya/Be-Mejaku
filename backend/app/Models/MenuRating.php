<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuRating extends Model
{
    protected $fillable = [
        'menu_item_id',
        'order_id',
        'customer_name',
        'rating',
        'review',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    /**
     * Menu item yang diulas.
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * Pesanan terkait (jika ada).
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
