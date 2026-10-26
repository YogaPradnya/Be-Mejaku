<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuPortion extends Model
{
    protected $fillable = [
        'menu_item_id',
        'name',
        'surcharge',
        'sort_order',
    ];

    protected $casts = [
        'surcharge' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Menu item yang memiliki porsi ini.
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
