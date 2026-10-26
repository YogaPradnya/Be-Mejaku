<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'table_id',
        'notes',
        'subtotal',
        'service_fee',
        'tax',
        'discount',
        'total',
        'promo_code',
        'delivery_type',
        'payment_status',
        'preparation_status',
        'idempotency_key',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'service_fee' => 'integer',
        'tax' => 'integer',
        'discount' => 'integer',
        'total' => 'integer',
    ];

    /**
     * Status pembayaran yang valid.
     */
    const PAYMENT_STATUSES = ['pending', 'checking', 'paid', 'failed', 'expired', 'cancelled'];

    /**
     * Status penyiapan yang valid.
     */
    const PREPARATION_STATUSES = ['received', 'preparing', 'ready', 'completed'];

    /**
     * Meja tempat pesanan ini.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    /**
     * Item-item dalam pesanan ini.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Data pembayaran pesanan ini (yang terbaru bila ada lebih dari satu).
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /**
     * Cek apakah pesanan sudah dibayar.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Cek apakah pesanan sudah selesai disiapkan.
     */
    public function isCompleted(): bool
    {
        return $this->preparation_status === 'completed';
    }
}
