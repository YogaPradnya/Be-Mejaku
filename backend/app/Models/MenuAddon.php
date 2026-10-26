<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuAddon extends Model
{
    protected $fillable = [
        'menu_item_id',
        'name',
        'price',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Menu item yang memiliki addon ini.
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
