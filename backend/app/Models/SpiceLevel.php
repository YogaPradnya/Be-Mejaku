<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpiceLevel extends Model
{
    protected $fillable = [
        'name',
        'level',
        'sort_order',
    ];

    protected $casts = [
        'level' => 'integer',
        'sort_order' => 'integer',
    ];
}
