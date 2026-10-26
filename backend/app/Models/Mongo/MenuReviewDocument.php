<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;

class MenuReviewDocument extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'menu_reviews';

    protected $fillable = [
        'menu_item_id',
        'menu_name',
        'order_number',
        'customer_name',
        'rating',
        'review',
        'tags',
        'metadata',
    ];

    protected $casts = [
        'rating' => 'integer',
        'menu_item_id' => 'integer',
        'tags' => 'array',
        'metadata' => 'array',
    ];
}
