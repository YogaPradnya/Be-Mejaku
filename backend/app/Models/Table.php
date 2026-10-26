<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends Model
{
    protected $fillable = [
        'code',
        'floor',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Pesanan yang terkait dengan meja ini.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
