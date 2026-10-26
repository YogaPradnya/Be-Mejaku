<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'method',
        'payment_code',
        'qr_data',
        'amount',
        'status',
        'expires_at',
        'paid_at',
        'snap_token',
        'redirect_url',
        'midtrans_order_id',
        'transaction_id',
        'payment_type',
        'fraud_status',
        'midtrans_response',
        'va_number',
        'va_bank',
    ];

    protected $casts = [
        'amount' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'midtrans_response' => 'array',
    ];

    /**
     * Metode pembayaran yang tersedia.
     */
    const METHODS = ['qris', 'bank_transfer', 'e_wallet', 'midtrans', 'cashier'];

    /**
     * Status pembayaran yang valid.
     */
    const STATUSES = ['pending', 'paid', 'failed', 'expired', 'cancelled'];

    /**
     * Pesanan yang terkait dengan pembayaran ini.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Cek apakah pembayaran sudah kedaluwarsa.
     */
    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * Cek apakah pembayaran sudah lunas.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
