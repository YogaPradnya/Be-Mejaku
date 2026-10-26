<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;

class OrderAuditLog extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'order_audit_logs';

    protected $fillable = [
        'order_number',
        'event',
        'details',
        'logged_at',
    ];

    protected $casts = [
        'details' => 'array',
        'logged_at' => 'datetime',
    ];
}
